<?php
/** Local synthetic setup: ddev drush php:script scripts/setup-contribution-demo.php. */
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
if (getenv('IS_DDEV_PROJECT')!=='true') throw new RuntimeException('Local DDEV only.');
$role=Role::load('lit_contribution_reviewer');
if (!$role) { $role=Role::create(['id'=>'lit_contribution_reviewer','label'=>'LiT contribution reviewer']);$role->grantPermission('review lit contributions')->grantPermission('access content')->save(); }
$account=\Drupal::currentUser();$previous=$account->getAccount();$account->setAccount(User::load(1));
try {
 $reviews=new \Drupal\lit_contribution\Service\ReviewManager(\Drupal::database(),\Drupal::service('lit_contribution.mock_source'),\Drupal::service('lit_contribution.validator'),\Drupal::currentUser(),\Drupal::service('private_key'),\Drupal::lock());
 $counts=$reviews->sync();
 echo json_encode($counts)."\nAll new/changed submissions remain pending; review and publish through Drupal.\n";
}finally{$account->setAccount($previous);}
