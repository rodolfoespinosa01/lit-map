<?php
/** Explicit transition cleanup. Does not change passwords, roles or survey data. */
if(PHP_SAPI!=='cli')throw new RuntimeException('Server CLI only.');
$owner=\Drupal::service('lit_contribution.owner');
$ids=\Drupal::entityQuery('user')->accessCheck(FALSE)->condition('roles',['lit_application_owner','lit_contribution_administrator'],'IN')->execute();
foreach($ids as $id)if((int)$id!==1)$owner->revoke((int)$id);
\Drupal::database()->delete('lit_contribution_login_token')->execute();
echo 'Legacy LiT tokens and application sessions revoked. No passwords, roles, review records or shared mail services changed.'.PHP_EOL;
