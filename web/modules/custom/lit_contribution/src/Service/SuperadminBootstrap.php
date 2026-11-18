<?php
namespace Drupal\lit_contribution\Service;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;

/** Explicit bootstrap/reset; never invoked by requests or cache rebuilding. */
final class SuperadminBootstrap {
  public function __construct(private readonly OwnerAccess $owner) {}
  public function run(#[\SensitiveParameter] array $values,bool $reset=FALSE): string {
    $gate=LocalOperationGate::acquire();
    if(PHP_SAPI!=='cli')throw new \RuntimeException('Server CLI only.');
    $email=strtolower(trim($values['LIT_SUPERADMIN_EMAIL']??''));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254)throw new \RuntimeException('Set a valid LIT_SUPERADMIN_EMAIL privately.');
    $ids=\Drupal::entityQuery('user')->accessCheck(FALSE)->condition('mail',$email)->execute();
    if(count($ids)>1)throw new \RuntimeException('Email belongs to multiple accounts; reconcile identities manually.');
    $existing=$ids?User::load(reset($ids)):NULL;$designated=$this->owner->ownerId();
    if($designated && (!$existing || (int)$existing->id()!==$designated))throw new \RuntimeException('Configured email does not match the existing owner. Ownership will not be reassigned.');
    if($existing && (int)$existing->id()===1)throw new \RuntimeException('UID 1 is reserved for emergency administration.');
    if($reset && !$designated)throw new \RuntimeException('Bootstrap the superadmin before resetting it.');
    $done=(int)\Drupal::state()->get('lit_contribution.password_bootstrapped_uid',0);
    if(!$reset && $designated && $done===$designated){
      if(!$existing->isActive() || !$existing->hasRole(OwnerAccess::OWNER_ROLE))throw new \RuntimeException('Existing superadmin requires explicit server recovery.');
      return 'Superadmin already bootstrapped; credentials and ownership unchanged.';
    }
    $password=$values['LIT_SUPERADMIN_INITIAL_PASSWORD']??'';
    if($password==='')throw new \RuntimeException('Set LIT_SUPERADMIN_INITIAL_PASSWORD privately before initial bootstrap or explicit reset.');
    PasswordPolicy::validate($password);
    if(!\Drupal::lock()->acquire('lit_contribution.superadmin_bootstrap',60))throw new \RuntimeException('Another bootstrap/reset is running.');
    \Drupal::state()->resetCache();
    if($this->owner->ownerId()!==$designated){\Drupal::lock()->release('lit_contribution.superadmin_bootstrap');throw new \RuntimeException('Ownership changed during setup; retry after inspecting the owner.');}
    $old=$designated;$oldDone=\Drupal::state()->get('lit_contribution.password_bootstrapped_uid',0);$tx=\Drupal::database()->startTransaction();
    try{
      // Recheck after acquiring the lock, preventing a concurrent setup reset.
      if(!$reset && (int)\Drupal::state()->get('lit_contribution.password_bootstrapped_uid',0)===$designated && $designated)return 'Superadmin already bootstrapped; credentials unchanged.';
      $roles=[OwnerAccess::OWNER_ROLE=>['access content','review lit contributions','synchronize lit contributions','publish lit contributions','administer lit contribution settings','manage lit administrators'],OwnerAccess::ADMIN_ROLE=>['access content','review lit contributions']];
      foreach($roles as $id=>$permissions){$r=Role::load($id);if(!$r){$r=Role::create(['id'=>$id,'label'=>$id===OwnerAccess::OWNER_ROLE?'LiT superadmin':'LiT administrator']);foreach($permissions as $p)$r->grantPermission($p);$r->save();}else{$actual=$r->getPermissions();sort($actual);sort($permissions);if($actual!==$permissions)throw new \RuntimeException('Existing role permissions differ; refusing to overwrite them.');}}
      $u=$existing??User::create(['name'=>'lit-superadmin-'.bin2hex(random_bytes(8)),'mail'=>$email,'status'=>1]);
      $u->setPassword($password);
      if($u->isNew())$u->save();
      \Drupal::state()->set('lit_contribution.owner_uid',(int)$u->id());
      $u->addRole(OwnerAccess::OWNER_ROLE);$u->activate();$u->save();
      $this->owner->revoke((int)$u->id());
      \Drupal::state()->set('lit_contribution.password_bootstrapped_uid',(int)$u->id());
      $this->owner->audit((int)$u->id(),$reset?'superadmin_password_reset':'superadmin_password_bootstrapped');
      unset($tx);return $reset?'Superadmin password reset; existing sessions revoked.':'Superadmin bootstrapped; existing sessions revoked. Initial password may now be removed from the environment.';
    }catch(\Throwable $e){$tx->rollBack();\Drupal::state()->set('lit_contribution.owner_uid',$old);\Drupal::state()->set('lit_contribution.password_bootstrapped_uid',$oldDone);throw new \RuntimeException('Bootstrap/reset failed safely; no credentials printed.',0,$e);}
    finally{\Drupal::lock()->release('lit_contribution.superadmin_bootstrap');unset($password);}
  }
}
