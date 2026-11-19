<?php
/** Explicit local setup; portable module installation never changes the front page. */
if(getenv('IS_DDEV_PROJECT')!=='true')throw new RuntimeException('Local DDEV setup only.');
$id=(int)(getenv('LIT_MAP_PAGE_ID')?:3);$node=\Drupal\node\Entity\Node::load($id);
if(!$node || !$node->isPublished() || !$node->hasField('field_lit_components'))throw new RuntimeException('Choose an existing public normal map page.');
\Drupal::state()->set('lit_map.local_page',$id);
\Drupal::configFactory()->getEditable('system.site')->set('page.front','/node/'.$id)->save();
echo "Local front page set to /node/$id. Standard login remains /user/login.\n";
