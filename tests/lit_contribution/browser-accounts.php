<?php
/** Local QA accounts, no emailed credentials. Random passwords are never saved. */
use Drupal\user\Entity\User;
if(getenv('IS_DDEV_PROJECT')!=='true')throw new RuntimeException('Local DDEV only.');
foreach(['lit_qa_reviewer'=>'lit_contribution_reviewer','lit_qa_viewer'=>NULL] as $name=>$role){
 $users=\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name'=>$name]);
 if(!$users){$u=User::create(['name'=>$name,'mail'=>$name.'@example.org','status'=>1,'pass'=>bin2hex(random_bytes(32))]);if($role)$u->addRole($role);$u->save();}
 else{$u=reset($users);if($u->getEmail()!==$name.'@example.org')throw new RuntimeException('QA account name collision.');}
 echo $name.': '.$u->id()."\n";
}
