<?php
/** Explicit local setup only; module installation never changes the front page. */
if(PHP_SAPI!=='cli' || getenv('IS_DDEV_PROJECT')!=='true')throw new RuntimeException('Local DDEV only.');
$config=\Drupal::configFactory()->getEditable('system.site');$state=\Drupal::state();$key='lit_contribution.previous_local_front';
if(getenv('LIT_RESTORE_FRONT')==='yes'){
  if($state->get($key)===NULL)throw new RuntimeException('No previous local front page saved.');
  $config->set('page.front',$state->get($key))->save();$state->delete($key);echo "Previous local front page restored.\n";
}else{
  if(!\Drupal::moduleHandler()->moduleExists('lit_contribution'))throw new RuntimeException('Enable LiT contribution first.');
  if($state->get($key)===NULL)$state->set($key,$config->get('page.front'));
  $config->set('page.front','/lit')->save();echo "Local front page now reuses /lit; previous value retained.\n";
}
\Drupal::service('cache_tags.invalidator')->invalidateTags(['config:system.site']);
