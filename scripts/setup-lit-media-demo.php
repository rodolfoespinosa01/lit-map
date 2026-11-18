<?php
/** Explicit test asset only. Changes only the demo Media bundle/access settings. */
use Drupal\media\Entity\{Media,MediaType};
use Drupal\field\Entity\{FieldStorageConfig,FieldConfig};
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
$bundle='lit_test_document';$type=MediaType::load($bundle);
if(!$type){$type=MediaType::create(['id'=>$bundle,'label'=>'LiT test document (not an interview)','source'=>'file','source_configuration'=>['source_field'=>'field_lit_test_file']]);$type->save();}
if(!FieldStorageConfig::loadByName('media','field_lit_test_file'))FieldStorageConfig::create(['entity_type'=>'media','field_name'=>'field_lit_test_file','type'=>'file','settings'=>['uri_scheme'=>'public']])->save();
if(!FieldConfig::loadByName('media',$bundle,'field_lit_test_file'))FieldConfig::create(['entity_type'=>'media','bundle'=>$bundle,'field_name'=>'field_lit_test_file','label'=>'Test document','settings'=>['file_extensions'=>'txt']])->save();
$display=EntityViewDisplay::load('media.'.$bundle.'.default')?:EntityViewDisplay::create(['targetEntityType'=>'media','bundle'=>$bundle,'mode'=>'default','status'=>TRUE]);$display->setComponent('field_lit_test_file',['type'=>'file_default','label'=>'hidden'])->save();
\Drupal::configFactory()->getEditable('media.settings')->set('standalone_url',TRUE)->save();
\Drupal\user\Entity\Role::load('anonymous')->grantPermission('view media')->save();
$ms=\Drupal::entityTypeManager()->getStorage('media');$existing=$ms->getQuery()->accessCheck(FALSE)->condition('bundle',$bundle)->condition('name','LiT explicit test document — not an interview')->execute();
if($existing)$media=$ms->load(reset($existing));else{
  $uri='public://lit-component-test.txt';file_put_contents($uri,"LiT component verification asset. This is a test document, not a recording or interview.\n");
  $file=File::create(['uri'=>$uri,'filename'=>'lit-component-test.txt','status'=>1]);$file->save();
  $media=Media::create(['bundle'=>$bundle,'name'=>'LiT explicit test document — not an interview','status'=>TRUE,'field_lit_test_file'=>['target_id'=>$file->id()]]);$media->save();
}
$ns=\Drupal::entityTypeManager()->getStorage('node');$ids=$ns->getQuery()->accessCheck(FALSE)->condition('type','lit_resource')->condition('field_lit_resource_key','local-test-document')->execute();
if($ids)$node=$ns->load(reset($ids));else{
  $english=NULL;foreach(\Drupal::service('lit_map.references')->terms('lit_languages') as $term)if($term->field_lit_language_key->value==='en')$english=$term->id();
  $node=Node::create(['type'=>'lit_resource','title'=>'Local test document — not an interview','status'=>TRUE,'field_lit_resource_key'=>'local-test-document','field_lit_kind'=>'resource','field_lit_test'=>TRUE,'field_lit_languages'=>[['target_id'=>$english]],'field_lit_media'=>['target_id'=>$media->id()]]);$node->save();
}
foreach($ns->loadMultiple($ns->getQuery()->accessCheck(FALSE)->condition('type','lit_demo_page')->execute()) as $page){foreach($page->field_lit_components->referencedEntities() as $paragraph){$ids=array_column($paragraph->field_lit_resources->getValue(),'target_id');if(!in_array($node->id(),$ids)){$paragraph->field_lit_resources[]=['target_id'=>$node->id()];$paragraph->save();}}}
\Drupal::service('router.builder')->rebuild();
echo 'Explicit test Media '.$media->id().' and resource '.$node->id()." attached to demo maps.\n";
