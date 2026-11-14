<?php
use Drupal\user\Entity\User;
use Drupal\lit_contribution\Service\OwnerAccess;
$owner=\Drupal::service('lit_contribution.owner')->ownerId();$checks=0;
foreach(['block','role','delete'] as $test){
 $u=User::load($owner);
 try{
   if($test==='delete')lit_contribution_user_predelete($u);
   else{if($test==='block')$u->block();else $u->removeRole(OwnerAccess::OWNER_ROLE);lit_contribution_user_presave($u);}
   throw new LogicException('Owner guard missing');
 }catch(RuntimeException $e){$checks++;}
 \Drupal::entityTypeManager()->getStorage('user')->resetCache([$owner]);
}
$fake=User::create(['name'=>'not-saved','mail'=>'not-saved@example.org','status'=>1,'roles'=>[OwnerAccess::OWNER_ROLE]]);
try{lit_contribution_user_presave($fake);throw new LogicException('Multiple owners accepted');}catch(RuntimeException $e){$checks++;}
if(!User::load($owner)->isActive() || !User::load($owner)->hasRole(OwnerAccess::OWNER_ROLE))throw new RuntimeException('Actual owner altered');$checks++;
$ip='isolated-owner-test-'.bin2hex(random_bytes(4));for($i=0;$i<20;$i++)\Drupal::flood()->register('lit.signin.ip',3600,$ip);
if(\Drupal::flood()->isAllowed('lit.signin.ip',20,3600,$ip))throw new RuntimeException('IP threshold not applied');$checks++;\Drupal::flood()->clear('lit.signin.ip',$ip);
echo "$checks owner/privilege/IP threshold checks passed; no user saved.\n";
