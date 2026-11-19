<?php
/** One-time pre-uninstall extraction; requires the old module and verified backup. */
$b=getenv('LIT_MIGRATION_BACKUP')?:DRUPAL_ROOT.'/../private/informational-backup-20261119';
if(!is_file($b.'/sha256.json'))throw new RuntimeException('Verified private backup required.');
foreach(json_decode(file_get_contents($b.'/sha256.json'),TRUE) as $file=>$hash)if(!hash_equals($hash,hash_file('sha256',$b.'/'.$file)))throw new RuntimeException('Backup checksum mismatch.');
$writer=\Drupal::service('lit_contribution.writer');$db=\Drupal::database();
$hash=$db->select('lit_contribution_publication','p')->fields('p',['file_hash'])->condition('id',1)->execute()->fetchField();
$d=$writer->read($hash);$active=['dataset_version'=>1,'aggregate'=>$d['aggregate'],'cohorts'=>$writer->read($d['questionnaire_hash'])['cohorts']];
file_put_contents($b.'/active-dataset.json',json_encode($active,JSON_THROW_ON_ERROR));chmod($b.'/active-dataset.json',0600);
$baseline=\Drupal::state()->get('lit_contribution.baseline');
$snapshot=\Drupal::service('lit_contribution.mock_source')->load($baseline);
if(count($snapshot)!==10000)throw new RuntimeException('Expected current 10,000-response baseline.');
$result=\Drupal\lit_map\Service\AggregateBuilder::build($snapshot,TRUE);
$demo=['dataset_version'=>1,'aggregate'=>$result['aggregate'],'cohorts'=>$result['questionnaire']];
if(array_sum(array_column($demo['aggregate']['groups'],'response_count'))!==10000)throw new RuntimeException('Baseline eligibility mismatch.');
\Drupal::service('lit_map.survey_normalizer')->normalize($demo['aggregate'],\Drupal::service('lit_contribution.validator')->counties(),\Drupal::service('lit_contribution.validator')->references());
file_put_contents(DRUPAL_ROOT.'/modules/custom/lit_map/data/demo-10000.v1.json',json_encode($demo,JSON_THROW_ON_ERROR)."\n");
$entities=[];foreach(['node','paragraph','taxonomy_term','media','user'] as $type)$entities[$type]=\Drupal::entityQuery($type)->accessCheck(FALSE)->execute();
file_put_contents($b.'/entity-ids.json',json_encode($entities));
echo json_encode(['baseline_responses'=>10000,'active_responses'=>array_sum(array_column($active['aggregate']['groups'],'response_count')),'baseline_matches_active'=>$demo===$active,'entity_counts'=>array_map('count',$entities)]).PHP_EOL;
