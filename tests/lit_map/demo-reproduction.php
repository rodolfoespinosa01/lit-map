<?php
/** Read-only comparison with deterministic synthetic input. */
$path=DRUPAL_ROOT.'/../private/lit-map-demo-source/submissions.json';
$rows=(new \Drupal\lit_map\Source\NormalizedExportMapper())->decode(file_get_contents($path),'json');$snapshot=[];
foreach($rows as $row){$s=\Drupal::service('lit_map.dataset_validator')->normalize($row);$snapshot[$s['survey_id'].'/'.$s['response_id']]=$s;}
$result=\Drupal\lit_map\Service\AggregateBuilder::build($snapshot,TRUE);
$expected=json_decode(file_get_contents(DRUPAL_ROOT.'/modules/custom/lit_map/data/demo-10000.v1.json'),TRUE);
foreach(['aggregate'=>$result['aggregate'],'cohorts'=>$result['questionnaire']] as $key=>$value)echo $key.': '.($value===$expected[$key]?'exact':($value==$expected[$key]?'key ordering differs':'values differ')).PHP_EOL;
if(count($snapshot)!==10000 || $result['aggregate']!==$expected['aggregate'] || $result['questionnaire']!==$expected['cohorts'])throw new RuntimeException('Demo reproduction mismatch.');
echo "PASS: generated 10,000-response aggregate, dates, unions and questionnaire cohorts exactly reproduce the retained baseline.\n";
