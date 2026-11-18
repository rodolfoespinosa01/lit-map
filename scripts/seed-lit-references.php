<?php
/** ddev drush php:script scripts/seed-lit-references.php */
$path = \Drupal::service('extension.list.module')->getPath('lit_map');
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$seed = function(string $vid, string $field, string $key, array $values) use ($storage): void {
  $ids = $storage->getQuery()->accessCheck(FALSE)->condition('vid',$vid)->condition($field,$key)->execute();
  if (count($ids)>1) throw new RuntimeException('Duplicate canonical key: '.$key);
  if ($ids) {
    // One-time seed upgrade retains historical spelling when editors rename terms.
    // Subsequent runs preserve deliberate alias edits.
    if($vid==='lit_languages' && !\Drupal::state()->get('lit_map.seed_aliases.v2.'.$key)){
      $term=$storage->load(reset($ids));$aliases=array_column($term->field_lit_aliases->getValue(),'value');
      $term->field_lit_aliases=array_values(array_unique(array_merge($aliases,$values['field_lit_aliases'])));$term->save();
      \Drupal::state()->set('lit_map.seed_aliases.v2.'.$key,TRUE);
    }
    return;
  }
  $term = $storage->create(['vid'=>$vid,$field=>$key]+$values);
  $errors=$term->validate();if(count($errors))throw new RuntimeException((string)$errors);
  $term->save();
  if($vid==='lit_languages')\Drupal::state()->set('lit_map.seed_aliases.v2.'.$key,TRUE);
};
foreach(json_decode(file_get_contents($path.'/data/nj-counties.json'),TRUE,512,JSON_THROW_ON_ERROR)['counties'] as $c) $seed('lit_counties','field_lit_geoid',$c['geoid'],['name'=>$c['name'],'field_lit_map_key'=>$c['key']]);
foreach(json_decode(file_get_contents($path.'/data/reference-seed.json'),TRUE,512,JSON_THROW_ON_ERROR) as $l) $seed('lit_languages','field_lit_language_key',$l['key'],['name'=>$l['label'],'field_lit_standard_code'=>$l['code'],'field_lit_aliases'=>array_values(array_unique(array_merge([$l['label']],$l['aliases']))),'field_lit_ambiguous'=>$l['ambiguous']]);
echo "Canonical terms seeded without overwriting existing terms. Chinese (zh) remains explicitly ambiguous.\n";
