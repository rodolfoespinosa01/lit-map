<?php
/** Explicit local/demo host setup; never invoked when the portable module installs. */
use Drupal\node\Entity\{Node,NodeType};
use Drupal\field\Entity\{FieldConfig,FieldStorageConfig};
use Drupal\Core\Entity\Entity\{EntityFormDisplay,EntityViewDisplay};
use Drupal\paragraphs\Entity\Paragraph;
$type=NodeType::load('lit_demo_page');
if(!$type){$type=NodeType::create(['type'=>'lit_demo_page','name'=>'LiT demonstration page','new_revision'=>TRUE]);$type->save();}
$storage=FieldStorageConfig::loadByName('node','field_lit_components');
if($storage && ($storage->getType()!=='entity_reference_revisions' || $storage->getSetting('target_type')!=='paragraph'))throw new RuntimeException('Conflicting field_lit_components storage.');
if(!$storage)FieldStorageConfig::create(['entity_type'=>'node','field_name'=>'field_lit_components','type'=>'entity_reference_revisions','cardinality'=>-1,'settings'=>['target_type'=>'paragraph']])->save();
if(!FieldConfig::loadByName('node','lit_demo_page','field_lit_components'))FieldConfig::create(['entity_type'=>'node','bundle'=>'lit_demo_page','field_name'=>'field_lit_components','label'=>'LiT maps','settings'=>['handler'=>'default:paragraph','handler_settings'=>['target_bundles'=>['lit_map'=>'lit_map']]]])->save();
$display=EntityFormDisplay::load('node.lit_demo_page.default')?:EntityFormDisplay::create(['targetEntityType'=>'node','bundle'=>'lit_demo_page','mode'=>'default','status'=>TRUE]);
$display->setComponent('title',['type'=>'string_textfield'])->setComponent('field_lit_components',['type'=>'paragraphs','settings'=>['title'=>'Map','title_plural'=>'Maps','edit_mode'=>'open','add_mode'=>'dropdown','form_display_mode'=>'default','default_paragraph_type'=>'lit_map']])->setComponent('status',['type'=>'boolean_checkbox'])->save();
$display=EntityViewDisplay::load('node.lit_demo_page.default')?:EntityViewDisplay::create(['targetEntityType'=>'node','bundle'=>'lit_demo_page','mode'=>'default','status'=>TRUE]);$display->setComponent('field_lit_components',['type'=>'entity_reference_revisions_entity_view','label'=>'hidden','settings'=>['view_mode'=>'default','link'=>FALSE]])->save();
$nodes=\Drupal::entityTypeManager()->getStorage('node');$ids=$nodes->getQuery()->accessCheck(FALSE)->condition('type','lit_demo_page')->condition('title','LiT reusable component demonstration')->execute();
if(!$ids){
  $source=\Drupal::hasService('lit_contribution.publication')?'published':'informational';
  $first=Paragraph::create(['type'=>'lit_map','field_lit_heading'=>'New Jersey language survey','field_lit_source'=>$source,'field_lit_municipalities'=>TRUE]);
  $second=Paragraph::create(['type'=>'lit_map','field_lit_heading'=>'Independent Bergen comparison','field_lit_source'=>$source,'field_lit_municipalities'=>FALSE]);
  $refs=\Drupal::service('lit_map.references');
  foreach($refs->terms('lit_counties') as $t)if($t->field_lit_geoid->value==='34003')$second->field_lit_counties=[['target_id'=>$t->id()]];
  foreach($refs->terms('lit_languages') as $t)if(in_array($t->field_lit_language_key->value,['en','es'],TRUE))$second->field_lit_languages[]=['target_id'=>$t->id()];
  $first->save();$second->save();
  $node=Node::create(['type'=>'lit_demo_page','title'=>'LiT reusable component demonstration','status'=>TRUE,'field_lit_components'=>[['target_id'=>$first->id(),'target_revision_id'=>$first->getRevisionId()],['target_id'=>$second->id(),'target_revision_id'=>$second->getRevisionId()]]]);$node->save();
}else $node=$nodes->load(reset($ids));
echo 'Normal page: /node/'.$node->id()."\nEditor: /node/".$node->id()."/edit\n";
