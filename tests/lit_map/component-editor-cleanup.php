<?php
$storage=\Drupal::entityTypeManager()->getStorage('node');
foreach($storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->condition('type','lit_demo_page')->condition('title','LiT editor verification — test page')->execute()) as $node){if($node->isPublished()){$node->setUnpublished()->save();}}
echo "Editor verification pages retained as unpublished drafts.\n";
