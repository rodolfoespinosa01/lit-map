<?php
use Drupal\lit_contribution\Service\{LocalReset,OwnerAccess,LocalOperationGate};
use Drupal\user\Entity\{User,Role};
ini_set('memory_limit','1024M');
if(!str_starts_with(DRUPAL_ROOT,'/tmp/lit-reset-qa.') || \Drupal::database()->getConnectionOptions()['driver']!=='sqlite')throw new RuntimeException('Disposable SQLite site only.');
$n=0;$ok=function($b,$label)use(&$n){if(!$b)throw new RuntimeException('Failed: '.$label);$n++;echo "PASS $label\n";};
$db=\Drupal::database();
if(!$db->schema()->tableExists('sessions')){$handler=\Drupal::service('session_handler.storage');$method=new ReflectionMethod($handler,'schemaDefinition');$method->setAccessible(TRUE);$db->schema()->createTable('sessions',$method->invoke($handler));}
$root=dirname(DRUPAL_ROOT);$reset=new LocalReset();
\Drupal::service('lit_contribution.superadmin_bootstrap')->run(['LIT_SUPERADMIN_EMAIL'=>'owner@example.org','LIT_SUPERADMIN_INITIAL_PASSWORD'=>'DemoOwnerPassword123!']);
$ownerId=\Drupal::service('lit_contribution.owner')->ownerId();$owner=User::load($ownerId);$hash=$owner->getPassword();
Role::create(['id'=>'unrelated_editor','label'=>'Unrelated editor'])->save();
$admin=User::create(['name'=>'placeholder-admin','mail'=>'admin@example.org','status'=>1]);$admin->setPassword('DemoAdminPassword123!');$admin->addRole(OwnerAccess::ADMIN_ROLE)->addRole('unrelated_editor')->save();$uid=(int)$admin->id();
$blocked=User::create(['name'=>'placeholder-blocked','mail'=>'blocked@example.org','status'=>0]);$blocked->addRole(OwnerAccess::ADMIN_ROLE)->save();
$db->insert('lit_contribution_access_audit')->fields(['target_uid'=>999999,'actor_uid'=>$ownerId,'action'=>'administrator_deactivated','occurred_at'=>time()])->execute();
$db->insert('sessions')->fields(['uid'=>$uid,'sid'=>str_repeat('a',43),'hostname'=>'127.0.0.1','timestamp'=>time(),'session'=>'disposable-session'])->execute();
\Drupal::state()->set('unrelated.test','preserve');\Drupal::configFactory()->getEditable('system.site')->set('page.front','/lit')->save();
$settings=\Drupal\Core\Site\Settings::getAll();new \Drupal\Core\Site\Settings(array_replace($settings,['lit_local_reset_enabled'=>FALSE]));
try{$reset->run('fresh');$ok(FALSE,'production guard');}catch(RuntimeException $e){$ok(TRUE,'production guard blocks mutation');}finally{new \Drupal\Core\Site\Settings($settings);}
$before=$db->select('lit_contribution_review','r')->countQuery()->execute()->fetchField();$reset->preview('fresh');$ok($before==$db->select('lit_contribution_review','r')->countQuery()->execute()->fetchField(),'preview does not mutate');
$backup=$reset->run('fresh');$generatedHash=hash_file('sha256',$root.'/private/lit-questionnaire-10000-v2/submissions.json');
$ok((int)$db->select('lit_contribution_review','r')->countQuery()->execute()->fetchField()===10000,'fresh has exactly 10000 responses');
$ok((int)$db->select('lit_contribution_review','r')->condition('status','pending')->countQuery()->execute()->fetchField()===0,'pending queue empty');
$ok(!User::load($uid)->hasRole(OwnerAccess::ADMIN_ROLE) && User::load($uid)->hasRole('unrelated_editor'),'admin loses LiT role; unrelated account and role survive');
$ok(!User::load($blocked->id())->hasRole(OwnerAccess::ADMIN_ROLE),'deactivated admin handled');
$ok(!$db->select('sessions','s')->condition('uid',$uid)->countQuery()->execute()->fetchField(),'admin sessions revoked');
$ok(User::load(1)!==NULL && User::load($ownerId)->getPassword()===$hash,'UID1 and owner credential hash preserved');
$ok(\Drupal::service('lit_contribution.password_login')->authenticate('owner@example.org','DemoOwnerPassword123!','127.0.0.1','superadmin')!==NULL,'existing owner password still authenticates');
$ok(\Drupal::state()->get('unrelated.test')==='preserve' && \Drupal::config('system.site')->get('page.front')==='/lit','unrelated state and landing preserved');
$ok(\Drupal::config('lit_contribution.settings')->get('contribution_mode')==='off' && \Drupal::config('lit_contribution.settings')->get('survey_url')==='','invitation off and external URL cleared');
// Use real import service and its duplicate reservation enforcement.
\Drupal::currentUser()->setAccount(User::load($ownerId));
$raw=json_decode(file_get_contents($root.'/fixtures/lit-export/questionnaire-v2.json'),TRUE)['submissions'][0];$raw['survey_id']='RESET_QA';$raw['response_id']='QA1';$raw['email']='submission@example.org';
$import=\Drupal::service('lit_contribution.importer');$plan=$import->preview([$raw]);$import->confirm([$raw],$plan['signature']);
$raw['response_id']='QA2';$ok($import->preview([$raw])['counts']['duplicate_email']===1,'duplicate reservation enforced before reset');
$source=\Drupal::service('lit_contribution.export_store')->snapshot();
$admin=User::load($uid);$admin->addRole(OwnerAccess::ADMIN_ROLE)->save();
$mode=\Drupal::config('lit_contribution.settings')->getRawData();
$importBackup=$reset->run('contributions');
$ok(User::load($uid)->hasRole(OwnerAccess::ADMIN_ROLE) && \Drupal::config('lit_contribution.settings')->getRawData()===$mode,'contributions preserves administrators and settings');
$ok(!glob($root.'/private/lit-imports/*.json'),'obsolete private source snapshots removed');
$ok(!$db->select('lit_contribution_email_reservation','r')->countQuery()->execute()->fetchField(),'contributions clears reservations');
$ok($import->preview([$raw])['counts']['new']===1,'same email can be accepted after reset');
$ok(count(\Drupal::service('lit_contribution.publication')->publicData()['survey']['combinations'])>0,'baseline republished');
$reset->restore($importBackup);
$ok(\Drupal::service('lit_contribution.export_store')->snapshot()===$source,'restore recovers private source bytes and pointer');
$ok($import->preview([$raw])['counts']['duplicate_email']===1,'restore recovers reservations');
$emptyBackup=$reset->run('empty');$public=\Drupal::service('lit_contribution.publication')->publicData();
$ok($public!==NULL && !$public['survey']['combinations'],'empty map has valid empty publication');
$ok(!\Drupal::service('lit_contribution.source')->listSubmissions(),'empty source usable');
$ok(!$db->select('lit_contribution_review','r')->countQuery()->execute()->fetchField(),'empty removes all active survey records');
$reset->restore($emptyBackup);$ok((int)$db->select('lit_contribution_review','r')->countQuery()->execute()->fetchField()===10001,'restore recovers baseline and operational review');
$reset->restore($backup);$ok(User::load($uid)->hasRole(OwnerAccess::ADMIN_ROLE) && (int)$db->select('sessions','s')->condition('uid',$uid)->countQuery()->execute()->fetchField()===1,'restore recovers roles and sessions');
// Symlink rejection before any backup or mutation.
symlink('/tmp',$root.'/private/lit-imports/unsafe');try{$reset->preview('empty');$ok(FALSE,'symlink rejected');}catch(RuntimeException $e){$ok(TRUE,'symlink rejected');}finally{unlink($root.'/private/lit-imports/unsafe');}
$gate=LocalOperationGate::acquire(TRUE);try{try{$reset->run('empty');$ok(FALSE,'exclusive gate');}catch(RuntimeException $e){$ok(TRUE,'concurrent reset blocked');}}finally{fclose($gate);}
// Leave disposable site in fresh state for browser QA.
$reset->run('fresh');
$ok(hash_file('sha256',$root.'/private/lit-questionnaire-10000-v2/submissions.json')===$generatedHash,'fresh baseline reproduces identical source bytes');
echo "$n disposable reset checks passed.\n";
