<?php
/** Component checks with explicit restoration; no dataset writes. */
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\taxonomy\Entity\Term;
$n=0;$ok=function($condition,$message)use(&$n){if(!$condition)throw new RuntimeException($message);$n++;};
$refs=\Drupal::service('lit_map.references');$nodes=\Drupal::entityTypeManager()->getStorage('node');$terms=\Drupal::entityTypeManager()->getStorage('taxonomy_term');
$pages=$nodes->loadMultiple($nodes->getQuery()->accessCheck(FALSE)->condition('type','lit_demo_page')->execute());$page=reset($pages);$ok((bool)$page,'Normal demo page exists');$paragraphs=$page->field_lit_components->referencedEntities();$ok(count($paragraphs)===2,'Two Paragraph references');
$ok(count($refs->counties()['counties'])===21,'21 canonical counties');$ok(count($refs->languages())>=11,'All historical language keys');$ok(($refs->aliases()['mandarin']??NULL)==='cmn' && ($refs->aliases()['cantonese']??NULL)==='yue' && !isset($refs->aliases()['chinese']),'Ambiguity preserved');
$before=\Drupal::service('lit_map.dataset')->publicData();
$originalStandalone=\Drupal::config('media.settings')->get('standalone_url');$originalHeading=$paragraphs[1]->field_lit_heading->value;
$originalEnglish=NULL;$originalMedia=NULL;
$tx=\Drupal::database()->startTransaction();
try{
  $all=$refs->terms('lit_languages');$english=array_values(array_filter($all,fn($t)=>$t->field_lit_language_key->value==='en'))[0];
  $originalEnglish=[$english->id(),$english->label()];
  $duplicate=Term::create(['vid'=>'lit_languages','name'=>'Duplicate English test','field_lit_language_key'=>'en']);$ok(count($duplicate->validate())>0,'Duplicate identifier rejected');
  $english->field_lit_language_key='xxx';$ok(count($english->validate())>0,'Stable key mutation rejected');$english->field_lit_language_key='en';
  $cache=\Drupal::cache('render');$cache->set('lit_component_test_term','old',time()+60,$english->getCacheTags());
  $english->setName('English editorial test label');$english->save();$ok(!$cache->get('lit_component_test_term'),'Term edit invalidates tagged cache');
  $build=\Drupal::service('lit_map.component')->build($paragraphs[0]->field_lit_source->value,$paragraphs[0]);$settings=reset($build['#attached']['drupalSettings']['litMapBubbles']);$labels=array_column($settings['survey']['languages'],'label','id');$ok($labels['en']==='English editorial test label','Taxonomy labels reach JavaScript');
  $ok(($refs->aliases()['english']??NULL)==='en','Historical alias survives an editorial label change');
  $ok(in_array('taxonomy_term:'.$english->id(),$build['#cache']['tags']),'Referenced term tag present');
  $ok(in_array('taxonomy_term_list:lit_languages',$build['#cache']['tags']),'All-language list tag present');
  $restricted=\Drupal::service('lit_map.component')->build($paragraphs[1]->field_lit_source->value,$paragraphs[1]);$s=reset($restricted['#attached']['drupalSettings']['litMapBubbles']);
  $ok(count($s['survey']['counties'])===1 && count($s['survey']['languages'])===2,'Restrictions applied');$ok(!array_diff(array_keys($s['survey']['totals']),['34003']),'Counts restricted');
  foreach($s['survey']['combinations']??[] as $g)$ok(!array_diff($g['languages'],['en','es']),'Combination keys restricted without summing marginals');
  $mediaNodes=$nodes->loadMultiple($nodes->getQuery()->accessCheck(FALSE)->condition('type','lit_resource')->condition('field_lit_resource_key','local-test-document')->execute());$resource=reset($mediaNodes);$ok((bool)$resource,'Native Media resource exists');
  $metadata=new CacheableMetadata();$cards=\Drupal::service('lit_map.resources')->cards([$resource],$metadata);$ok(count($cards)===1,'Published accessible Media rendered');
  $media=$resource->field_lit_media->entity;$originalMedia=[$media->id(),$media->isPublished()];$cache->set('lit_component_test_media','old',time()+60,$media->getCacheTags());$media->setUnpublished()->save();
  $ok(!$cache->get('lit_component_test_media'),'Media update invalidates tagged cache');$ok(!\Drupal::service('lit_map.resources')->cards([$resource],new CacheableMetadata()),'Unpublished Media omitted');
  $media->setPublished()->save();\Drupal::configFactory()->getEditable('media.settings')->set('standalone_url',FALSE)->save();
  $ok(!\Drupal::service('lit_map.resources')->cards([$resource],new CacheableMetadata()),'Inaccessible canonical Media omitted');
  $resource->field_lit_media=[];$resource->field_lit_external=['uri'=>'http://example.org/unsafe'];$ok(count($resource->validate())>0,'Non-HTTPS editorial link rejected');
  $resource->field_lit_external=['uri'=>'https://vimeo.com/123456'];$resource->field_lit_transcript=['uri'=>'https://example.org/transcript'];
  $external=\Drupal::service('lit_map.resources')->cards([$resource],new CacheableMetadata());
  $ok(count($external)===1 && $external[0]['card']['action']['#url']->toString()==='https://vimeo.com/123456','Native HTTPS external resource renders a Drupal link');
  $ok(isset($external[0]['card']['transcript']),'Native transcript link renders');
  $cache->set('lit_component_test_publication','old',time()+60,['lit_map:dataset']);\Drupal::service('cache_tags.invalidator')->invalidateTags(['lit_map:dataset']);$ok(!$cache->get('lit_component_test_publication'),'Dataset tag invalidates map caches');
  $cache->set('lit_component_test_paragraph','old',time()+60,$paragraphs[1]->getCacheTags());$paragraphs[1]->field_lit_heading='Changed heading';$paragraphs[1]->save();$ok(!$cache->get('lit_component_test_paragraph'),'Paragraph edit invalidates cache');
  if($before){$now=\Drupal::service('lit_map.dataset')->publicData();$ok($before['hash']===$now['hash'] && $before['survey']['combinations']===$now['survey']['combinations'],'Published groups and pointer preserved');}
}catch(\Throwable $failure){echo 'Component test failed: '.$failure->getMessage().PHP_EOL;throw $failure;}finally{
  // Router rebuilds triggered by Media configuration can commit DDL on MariaDB.
  try{$tx->rollBack();}catch(\Throwable $rollback){if(!str_contains($rollback->getMessage(),'Active stack: *** empty ***'))throw $rollback;}
  \Drupal::configFactory()->reset();$nodes->resetCache();$terms->resetCache();
  if($originalEnglish){$term=$terms->load($originalEnglish[0]);if($term->label()!==$originalEnglish[1])$term->setName($originalEnglish[1])->save();}
  if($originalMedia){$storage=\Drupal::entityTypeManager()->getStorage('media');$storage->resetCache();$m=$storage->load($originalMedia[0]);if($m->isPublished()!==$originalMedia[1]){$m->set('status',$originalMedia[1]);$m->save();}}
  $storage=\Drupal::entityTypeManager()->getStorage('paragraph');$storage->resetCache();$p=$storage->load($paragraphs[1]->id());if($p->field_lit_heading->value!==$originalHeading){$p->field_lit_heading=$originalHeading;$p->save();}
  if(\Drupal::config('media.settings')->get('standalone_url')!==$originalStandalone)\Drupal::configFactory()->getEditable('media.settings')->set('standalone_url',$originalStandalone)->save();
  \Drupal::service('lit_map.references')->reset();\Drupal::service('cache_tags.invalidator')->invalidateTags(['taxonomy_term_list','node_list','media_list','lit_map:dataset']);
}

echo "$n component integration checks passed on Drupal ".\Drupal::VERSION."; test edits restored.\n";
