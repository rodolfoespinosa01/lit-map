<?php
require __DIR__ . '/../../web/modules/custom/lit_map/src/Service/SurveyNormalizer.php';
use Drupal\lit_map\Service\SurveyNormalizer;
$dir=__DIR__.'/../../web/modules/custom/lit_map/data/';
$read=fn($name)=>json_decode(file_get_contents($dir.$name),TRUE,512,JSON_THROW_ON_ERROR);
$fixture=$read('survey.statewide.v2.json');
$data=(new SurveyNormalizer())->normalize($fixture,$read('nj-counties.json'),$read('survey-statewide-references.json'));
$report=json_decode(file_get_contents(__DIR__.'/../../docs/bubbles/fixture-report.json'),TRUE,512,JSON_THROW_ON_ERROR);
$checks=0;
function verify($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
verify(count($data['municipalities'])===564 && count($data['totals'])===21,'Complete statewide coverage');
verify(count($fixture['records'])===1791,'Reproducible record count');
foreach($data['totals'] as $county=>$languages){
 foreach($languages as $language=>$total){
  $sum=0;$unspecified=0;
  foreach($fixture['records'] as $record){
   if($record['county_geoid']===(string)$county && $record['language']===$language){$sum+=$record['response_count'];if($record['municipality']===NULL)$unspecified+=$record['response_count'];}
  }
  verify($total===$sum,'Independent raw-record total');
  verify($data['breakdown'][$county]['_unspecified'][$language]===$unspecified,'Unspecified included once');
  verify($total===$report['countyTotals'][$county][$language],'Reproducible seed report');
 }
}
foreach($report['municipalitiesWithNoRecords'] as $id){foreach($data['breakdown'] as $parts)verify(!isset($parts[$id]),'Missing stays absent');}
foreach($report['explicitZeroExamples'] as [$id,$language]){
 $county=$read('bubble-geography.json')['municipalities'][$id]['county'];
 verify(array_key_exists($language,$data['breakdown'][$county][$id]) && $data['breakdown'][$county][$id][$language]===0,'Explicit zero retained');
}
verify(!isset($data['records']),'Only public normalized display data');
echo "$checks statewide aggregation checks passed.\n";
