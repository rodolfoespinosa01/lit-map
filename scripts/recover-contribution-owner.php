<?php
/** Server-only emergency repair/transfer. Never a public form or automatic action. */
use Drupal\user\Entity\User;
use Drupal\lit_contribution\Service\OwnerAccess;
if(PHP_SAPI!=='cli' || getenv('IS_DDEV_PROJECT')!=='true' || getenv('LIT_CONFIRM_OWNER_RECOVERY')!=='yes')throw new RuntimeException('Explicit local server recovery confirmation required.');
$uid=(int)getenv('LIT_OWNER_UID');$u=User::load($uid);if(!$u || !$uid)throw new RuntimeException('Choose an existing confirmed owner UID.');
$owner=\Drupal::service('lit_contribution.owner');$old=$owner->ownerId();
$account=\Drupal::service('account_switcher');$account->switchTo(User::load(1));
try{
 if(!\Drupal::lock()->acquire('lit_contribution.owner_recovery',60))throw new RuntimeException('Another recovery is running.');
 $tx=\Drupal::database()->startTransaction();
 try{
   \Drupal::state()->set('lit_contribution.owner_uid',$uid);
   if($old && $old!==$uid && ($previous=User::load($old))){$previous->removeRole(OwnerAccess::OWNER_ROLE);$previous->save();$owner->revoke($old);}
   $u->addRole(OwnerAccess::OWNER_ROLE);$u->activate();$u->save();$owner->revoke($uid);$owner->audit($uid,'server_owner_recovery');unset($tx);
 }catch(Throwable $e){$tx->rollBack();\Drupal::state()->set('lit_contribution.owner_uid',$old);throw $e;}
 finally{\Drupal::lock()->release('lit_contribution.owner_recovery');}
 echo 'Owner repaired/transferred to UID '.$uid.'. Existing sessions/links revoked; use the explicit environment-based superadmin password-reset command. UID 1 emergency login remains available.'.PHP_EOL;
}finally{$account->switchBack();}
