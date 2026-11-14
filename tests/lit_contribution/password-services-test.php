<?php
/** Disposable credentials, transaction rollback, and process-local owner state. No secret output. */
use Drupal\Core\State\StateInterface;
use Drupal\lit_contribution\Service\BootstrapEnvironment;
use Drupal\lit_contribution\Service\PasswordPolicy;
use Drupal\user\Entity\User;
$checks=0;$check=function(bool $ok,string $reason)use(&$checks){if(!$ok)throw new RuntimeException($reason);$checks++;};
$state=\Drupal::state();$container=\Drupal::getContainer();$current=\Drupal::currentUser();$oldAccount=$current->getAccount();
$realOwner=User::load($state->get('lit_contribution.owner_uid'));$realHash=$realOwner->getPassword();$realOwnerId=$realOwner->id();
$fake=new class implements StateInterface {
  public array $values=[];
  public function get($key,$default=NULL){return $this->values[$key]??$default;}
  public function getMultiple(array $keys){return array_intersect_key($this->values,array_flip($keys));}
  public function set($key,$value){$this->values[$key]=$value;}
  public function setMultiple(array $data){$this->values=array_replace($this->values,$data);}
  public function delete($key){unset($this->values[$key]);}
  public function deleteMultiple(array $keys){foreach($keys as $key)$this->delete($key);}
  public function resetCache(){}
};
$container->set('state',$fake);$db=\Drupal::database();$tx=$db->startTransaction();$ids=[];
try {
  $bootstrap=\Drupal::service('lit_contribution.superadmin_bootstrap');$owner=\Drupal::service('lit_contribution.owner');$login=\Drupal::service('lit_contribution.password_login');
  $password=bin2hex(random_bytes(24)).'Q7';$next=bin2hex(random_bytes(24)).'W8';$email='bootstrap-'.bin2hex(random_bytes(6)).'@example.org';
  $values=['LIT_SUPERADMIN_EMAIL'=>$email,'LIT_SUPERADMIN_INITIAL_PASSWORD'=>$password];
  foreach([[],['LIT_SUPERADMIN_EMAIL'=>'invalid'],['LIT_SUPERADMIN_EMAIL'=>$email],array_replace($values,['LIT_SUPERADMIN_INITIAL_PASSWORD'=>'short'])] as $bad){try{$bootstrap->run($bad);throw new LogicException('Invalid bootstrap accepted');}catch(InvalidArgumentException|RuntimeException $e){$checks++;}}
  $bootstrap->run($values);$uid=$owner->ownerId();$ids[]=$uid;$u=User::load($uid);
  $check($u->isActive() && $u->hasRole('lit_application_owner'),'Disposable owner created');
  $check(\Drupal::service('password')->check($password,$u->getPassword()) && $password!==$u->getPassword(),'Drupal hashes password');
  $hash=$u->getPassword();$bootstrap->run(array_replace($values,['LIT_SUPERADMIN_INITIAL_PASSWORD'=>$next]));User::load($uid);$check(User::load($uid)->getPassword()===$hash,'Setup rerun does not reset');
  $bootstrap->run(['LIT_SUPERADMIN_EMAIL'=>$email]);$check(User::load($uid)->getPassword()===$hash,'Initial password may be removed');
  $session=function($id)use($db){$db->insert('sessions')->fields(['uid'=>$id,'sid'=>bin2hex(random_bytes(32)),'hostname'=>'isolated','timestamp'=>time(),'session'=>'isolated-test'])->execute();};
  $session($uid);$bootstrap->run(array_replace($values,['LIT_SUPERADMIN_INITIAL_PASSWORD'=>$next]),TRUE);
  $check(\Drupal::service('password')->check($next,User::load($uid)->getPassword()) && !\Drupal::service('password')->check($password,User::load($uid)->getPassword()),'Explicit owner reset replaces credentials');
  $check((int)$db->select('sessions','s')->condition('uid',$uid)->countQuery()->execute()->fetchField()===0,'Owner reset revokes sessions');
  $check((int)$login->authenticate($email,$next,'isolated-owner','superadmin')?->id()===$uid,'Owner email/password authentication');
  $check($login->authenticate($email,$next,'isolated-owner-mode','administrator')===NULL,'Owner cannot enter administrator mode');
  $current->setAccount(User::load($uid));$adminEmail='administrator-'.bin2hex(random_bytes(6)).'@example.org';$admin=$owner->add($adminEmail,$password);$ids[]=$admin;
  $check((int)$login->authenticate($adminEmail,$password,'isolated-admin','administrator')?->id()===$admin,'Assigned administrator credentials');
  $check($login->authenticate($adminEmail,'','isolated-blank','administrator')===NULL,'Email alone fails');
  $check($login->authenticate($adminEmail,'incorrect','isolated-wrong','administrator')===NULL,'Wrong password fails');
  $check($login->authenticate($adminEmail,$password,'isolated-mode','superadmin')===NULL,'Administrator cannot authenticate as superadmin');
  $owner->setActive($admin,FALSE);$check($login->authenticate($adminEmail,$password,'isolated-blocked','administrator')===NULL,'Inactive administrator denied');$owner->setActive($admin,TRUE);
  $session($admin);$owner->resetPassword($admin,$next);$check((int)$db->select('sessions','s')->condition('uid',$admin)->countQuery()->execute()->fetchField()===0,'Admin reset revokes sessions');
  $check(!\Drupal::service('password')->check($password,User::load($admin)->getPassword()) && \Drupal::service('password')->check($next,User::load($admin)->getPassword()),'Admin old/new credential check');
  $current->setAccount(User::load($admin));try{$owner->add('forbidden@example.org',$password);throw new LogicException('Admin escalation');}catch(Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e){$checks++;}
  $check(!User::load($admin)->access('update',$current),'Admin cannot select own password through core account edit');
  $current->setAccount(User::load($uid));
  try{$bootstrap->run(['LIT_SUPERADMIN_EMAIL'=>$adminEmail,'LIT_SUPERADMIN_INITIAL_PASSWORD'=>$password]);throw new LogicException('Ownership reassignment');}catch(RuntimeException $e){$checks++;}
  $fake->delete('lit_contribution.password_bootstrapped_uid');$bootstrap->run(array_replace($values,['LIT_SUPERADMIN_INITIAL_PASSWORD'=>$next]));$check($owner->ownerId()===$uid,'Matching intended owner reused');
  $duplicate=User::create(['name'=>'duplicate-'.bin2hex(random_bytes(5)),'mail'=>$email,'status'=>1,'pass'=>$password]);$duplicate->save();$ids[]=$duplicate->id();
  try{$bootstrap->run($values);throw new LogicException('Duplicate identity accepted');}catch(RuntimeException $e){$checks++;}
  $blockedEmail='throttle-'.bin2hex(random_bytes(5)).'@example.org';for($i=0;$i<5;$i++)$login->authenticate($blockedEmail,$password,'isolated-throttle','administrator');
  $check(!\Drupal::flood()->isAllowed('lit.password.email',5,900,hash('sha256',$blockedEmail)),'Unknown email throttled generically');
  $check(\Drupal::service('lit_contribution.passwordless')->consume(str_repeat('a',64),'isolated')===NULL,'Legacy token service disabled');
  $message=['module'=>'user','key'=>'password_reset','params'=>['account'=>User::load($admin)],'send'=>TRUE];lit_contribution_mail_alter($message);$check($message['send']===FALSE,'Managed account reset mail suppressed');
  $message['params']['account']=User::load(1);$message['send']=TRUE;lit_contribution_mail_alter($message);$check($message['send']===TRUE,'Emergency UID 1 mail unaffected');
  $literal=BootstrapEnvironment::parse("LIT_SUPERADMIN_EMAIL=example@example.org\nLIT_SUPERADMIN_INITIAL_PASSWORD='literal-\$(not-run)-`not-run`-123'\nUNRELATED=ignored\n");$check(count($literal)===2 && str_contains($literal['LIT_SUPERADMIN_INITIAL_PASSWORD'],'$(not-run)'),'Loader literal/allowlist');
  try{BootstrapEnvironment::parse("LIT_SUPERADMIN_EMAIL=a\nLIT_SUPERADMIN_EMAIL=b");throw new LogicException('Duplicate env accepted');}catch(RuntimeException $e){$checks++;}
  echo "$checks isolated password/bootstrap/role/environment checks passed. No credentials printed.\n";
} finally {
  $current->setAccount($oldAccount);$tx->rollBack();$container->set('state',$state);\Drupal::entityTypeManager()->getStorage('user')->resetCache();
  if(User::load($realOwnerId)->getPassword()!==$realHash || \Drupal::state()->get('lit_contribution.owner_uid')!=$realOwnerId)throw new RuntimeException('Live owner preservation failed');
  echo "Live owner credentials and ownership preserved; disposable user/session/audit changes rolled back.\n";
}
