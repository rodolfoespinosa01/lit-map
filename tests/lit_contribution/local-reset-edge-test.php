<?php
use Drupal\lit_contribution\Service\{LocalReset,LocalOperationGate};
ini_set('memory_limit','1024M');
if(!str_starts_with(DRUPAL_ROOT,'/tmp/lit-reset-qa.') || \Drupal::database()->getConnectionOptions()['driver']!=='sqlite')throw new RuntimeException('Disposable SQLite only.');
$r=new LocalReset();$root=dirname(DRUPAL_ROOT);$state=\Drupal::state();$baseline=$state->get('lit_contribution.baseline');$before=\Drupal::service('lit_contribution.publication')->publicData()['hash'];
$state->set('lit_contribution.baseline','invalid-test-source');
try{$r->run('contributions');throw new LogicException('Expected failed mutation.');}catch(RuntimeException $e){if(!str_contains($e->getMessage(),'original data restored'))throw $e;}finally{$state->set('lit_contribution.baseline',$baseline);}
if(\Drupal::service('lit_contribution.publication')->publicData()['hash']!==$before || file_exists($root.'/private/lit-reset-incomplete'))throw new RuntimeException('Failure recovery mismatch.');
echo "PASS failed mutation restores publication and removes fence\n";
$id=$r->run('contributions');$checksum=$root.'/private/lit-reset-backups/'.$id.'.backup.sha256';$original=file_get_contents($checksum);file_put_contents($checksum,str_repeat('0',64));
try{$r->restore($id);throw new LogicException('Corruption accepted');}catch(RuntimeException $e){}finally{file_put_contents($checksum,$original);}
echo "PASS corrupted backup rejected before mutation\n";
file_put_contents($root.'/private/lit-reset-incomplete',$id);
try{LocalOperationGate::acquire();throw new LogicException('Fence bypass');}catch(RuntimeException $e){}
$r->restore($id);if(file_exists($root.'/private/lit-reset-incomplete'))throw new RuntimeException('Fence not cleared');
echo "PASS interrupted-operation fence and explicit recovery\n";
$role=\Drupal\user\Entity\Role::load('unrelated_editor');$role->grantPermission('review lit contributions')->save();
$u=\Drupal\user\Entity\User::load(3);$u->removeRole('lit_contribution_administrator')->save();$previous=\Drupal::currentUser()->getAccount();\Drupal::currentUser()->setAccount($u);
if(!$u->hasPermission('review lit contributions') || \Drupal::service('lit_contribution.owner')->reviewAccess()->isAllowed())throw new RuntimeException('Unrelated permission bypassed revoked LiT membership.');
try{\Drupal::service('lit_contribution.reviews')->requirePermission('review lit contributions');throw new LogicException('Revoked access accepted');}catch(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e){}
\Drupal::currentUser()->setAccount($previous);
echo "PASS revoked LiT access stays denied despite an unrelated Drupal role permission\n";
$u=\Drupal\user\Entity\User::load(3);$u->addRole('lit_contribution_administrator')->setPassword('DemoAdminPassword123!')->activate()->save();
