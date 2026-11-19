<?php
/** Drush integration: restores the original snapshot even on failure. */
$service=\Drupal::service('lit_map.dataset');$original=$service->document();$pointer=\Drupal::state()->get('lit_map.dataset');$checks=0;
$ok=function($condition,$message)use(&$checks){if(!$condition)throw new RuntimeException($message);$checks++;};
try {
 $demo=json_decode(file_get_contents(DRUPAL_ROOT.'/modules/custom/lit_map/data/demo-10000.v1.json'),TRUE);
 $dto=$service->validate($demo);$ok(array_sum(array_column($dto['combinations'],'count'))===10000,'Demo count');
 $q=\Drupal\lit_map\Service\Questionnaire::blank();$q['q01']='yes';$q['q03']='Essex';$q['q07'][0]=['text'=>'English','age'=>NULL];
 $row=['schema_version'=>2,'survey_id'=>'ISOLATED_TEST','response_id'=>'R_1','submitted_at'=>NULL,'updated_at'=>NULL,'answers'=>['consent'=>TRUE,'map_permission'=>'yes','questions'=>$q,'resolutions'=>[]]];
 $bytes=json_encode(['export_version'=>2,'submissions'=>[$row,$row]]);
 $hash=$service->import($bytes,'json',TRUE);$ok($service->import($bytes,'json',TRUE)===$hash,'Reimport idempotence');
 $data=$service->publicData();$ok(array_sum(array_column($data['survey']['combinations'],'count'))===1,'Duplicate source IDs count once');
 $ok($data['survey']['days'][0]['day']===NULL,'Missing dates stay undated');
 $row['response_id']='R_2';$service->import(json_encode(['export_version'=>2,'submissions'=>[$row]]),'json');
 $ok(array_sum(array_column($service->publicData()['survey']['combinations'],'count'))===1,'Full replacement does not append');
 $ok($service->publicData()['survey']['synthetic']===FALSE,'Non-synthetic metadata retained');
 $last=\Drupal::state()->get('lit_map.dataset');
 foreach(['{}',json_encode(['export_version'=>2,'submissions'=>[array_merge($row,['email'=>'must-not-be-stored@example.org'])]])] as $invalid){try{$service->import($invalid,'json');throw new LogicException('Invalid file accepted');}catch(InvalidArgumentException|UnexpectedValueException $e){}$ok(\Drupal::state()->get('lit_map.dataset')===$last,'Invalid data preserves pointer');}
 $bad=$service->document();$bad['aggregate']['groups'][0]['response_count']++;
 try{$service->activate($bad);throw new LogicException('Bad counts accepted');}catch(InvalidArgumentException $e){}$ok(\Drupal::state()->get('lit_map.dataset')===$last,'Inconsistent aggregate rejected');
 $public=json_encode($service->publicData());$ok(!str_contains($public,'R_2')&&!str_contains($public,'ISOLATED_TEST')&&!str_contains($public,'email'),'Public allowlist');
 $ok(\Drupal\lit_map\Service\EditorialResources::safeUrl('https://example.org/qualtrics-test'),'Test HTTPS URL');$ok(!\Drupal\lit_map\Service\EditorialResources::safeUrl('http://example.org/test'),'Reject HTTP');
 foreach(['example','questionnaire-v2'] as $fixture){
  $dir=DRUPAL_ROOT.'/../fixtures/lit-export/';
  $json=$service->import(file_get_contents($dir.$fixture.'.json'),'json',TRUE);
  $csv=$service->import(file_get_contents($dir.$fixture.'.csv'),'csv',TRUE);
  $ok($json===$csv,'CSV and JSON equivalents');
 }
 echo "PASS: $checks dataset assertions.\n";
} finally {if($original){$service->activate($original);\Drupal::state()->set('lit_map.dataset',$pointer);}}
