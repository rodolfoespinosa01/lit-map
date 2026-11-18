<?php
/** Explicit idempotent catalogue migration. Retains original config as archive. */
use Drupal\node\Entity\Node;
$catalog=\Drupal::hasService('lit_contribution.resources')?\Drupal::service('lit_contribution.resources'):NULL;
if(!$catalog){echo "No legacy catalogue installed.\n";return;}
$items=$catalog->validate($catalog->json());$storage=\Drupal::entityTypeManager()->getStorage('node');$terms=\Drupal::entityTypeManager()->getStorage('taxonomy_term');
$resolve=function($vid,$field,$key)use($terms){$ids=$terms->getQuery()->accessCheck(FALSE)->condition('vid',$vid)->condition($field,$key)->execute();if(count($ids)!==1)throw new RuntimeException('Unresolved resource term: '.$key);return reset($ids);};
foreach($items as $item){
  $ids=$storage->getQuery()->accessCheck(FALSE)->condition('type','lit_resource')->condition('field_lit_resource_key',$item['id'])->execute();
  if(count($ids)>1)throw new RuntimeException('Duplicate resource key.');if($ids)continue;
  $values=['type'=>'lit_resource','title'=>$item['title'],'status'=>$item['approved'] && $item['target']!=='placeholder','field_lit_resource_key'=>$item['id'],'field_lit_description_long'=>$item['description'],'field_lit_kind'=>$item['kind'],'field_lit_test'=>$item['test_only'],'field_lit_municipality'=>$item['municipality'],'field_lit_languages'=>array_map(fn($l)=>['target_id'=>$resolve('lit_languages','field_lit_language_key',$l)],$item['languages'])];
  if($item['county'])$values['field_lit_counties']=['target_id'=>$resolve('lit_counties','field_lit_geoid',$item['county'])];
  if($item['url'])$values['field_lit_external']=['uri'=>$item['url']];
  if($item['transcript_url'])$values['field_lit_transcript']=['uri'=>$item['transcript_url']];
  if($item['media_id']){
    if(!\Drupal::entityTypeManager()->getStorage('media')->load($item['media_id']))throw new RuntimeException('Unresolved legacy Media ID for '.$item['id'].'. Catalogue retained; repair and rerun.');
    $values['field_lit_media']=['target_id'=>$item['media_id']];
  }
  $node=Node::create($values);$violations=$node->validate();if(count($violations))throw new RuntimeException((string)$violations);$node->save();
  echo $item['id'].': migrated '.($node->isPublished()?'published resource':'unpublished draft')."\n";
}
echo "Original catalogue retained. Existing migrated resources were not overwritten.\n";
