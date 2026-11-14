<?php
// Pure deterministic test: does not bootstrap Drupal or alter demonstration data.
require __DIR__.'/../../web/modules/custom/lit_contribution/src/Service/AggregateBuilder.php';
require __DIR__.'/../../web/modules/custom/lit_contribution/src/Service/AtomicAggregateWriter.php';
use Drupal\lit_contribution\Service\AggregateBuilder as B;
use Drupal\lit_contribution\Service\AtomicAggregateWriter;
$checks=0;$check=function($ok,$label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$source=[];$rows=[];
// id / status / municipality / English / Spanish / permission / duplicate.
// Eligible 1,2,3: English county 2 = town 1 + unspecified 1;
// Spanish county 1 = town 1 + unspecified explicit zero. Korean is missing.
$fixture=[
 [1,'approved','m3400300700','yes','yes','yes',0],
 [2,'approved','m3400300700','no','prefer_not_to_answer','yes',0],
 [3,'approved',NULL,'yes','no','yes',0],
 [4,'pending','m3400300700','yes','yes','yes',0],
 [5,'rejected','m3400300700','yes','yes','yes',0],
 [6,'approved','m3400300700','yes','yes','yes',1],
 [7,'approved','m3400300700','yes','yes','no',0],
];
foreach($fixture as [$id,$status,$town,$en,$es,$permission,$duplicate]) {
 $source['survey/'.$id]=['answers'=>['county_geoid'=>'34003','municipality'=>$town,'aggregate_permission'=>$permission,'languages'=>['en'=>['uses_language'=>$en],'es'=>['uses_language'=>$es]]]];
 $rows[$id]=['id'=>$id,'survey_id'=>'survey','response_id'=>(string)$id,'status'=>$status,'duplicate_conflict'=>$duplicate,'duplicate_choice'=>0];
}
$result=B::build($source,$rows);$check($result['included']===[1,2,3],'Only approved permitted nonduplicates');
$sum=[];$buckets=[];foreach($result['aggregate']['records'] as $r){$sum[$r['language']]=($sum[$r['language']]??0)+$r['response_count'];$buckets[($r['municipality']??'_unspecified').'/'.$r['language']]=$r['response_count'];}
$check($sum===['en'=>2,'es'=>1],'Hand-calculated county totals');
$check($buckets===['_unspecified/en'=>1,'_unspecified/es'=>0,'m3400300700/en'=>1,'m3400300700/es'=>1],'Town plus unspecified, explicit zero');
$check(!isset($sum['ko']),'Missing language is not inferred zero');
$rows[6]['duplicate_conflict']=0;$rows[6]['duplicate_choice']=1;
$check(B::build($source,$rows)===$result,'Resolved nonchosen duplicate remains excluded');
$rows[1]['status']='rejected';$next=B::build($source,$rows);
$check($next['included']===[2,3],'Subsequent rejection removes previously approved record');
$rows[4]['status']='approved';$check(B::build($source,$rows)['included']===[2,3,4],'Subsequent approval adds eligible record');
$source['survey/3']['answers']['aggregate_permission']='no';$check(B::build($source,$rows)['included']===[2,4],'Permission withdrawal removes record');
// Isolated immutable storage only; production publication pointer is untouched.
$base=sys_get_temp_dir().'/lit-reconciliation-'.bin2hex(random_bytes(6));mkdir($base.'/web',0700,TRUE);
$writer=new AtomicAggregateWriter($base.'/web');
$doc=['publication_version'=>1,'updated_date'=>'2026-11-13','aggregate'=>$result['aggregate']];
$hash=$writer->write($doc);$check($writer->read($hash)===$doc,'Original publication round trip');
$new=$doc;$new['aggregate']=$next['aggregate'];$newHash=$writer->write($new);
$check($newHash!==$hash && $writer->read($hash)===$doc,'Next publication preserves prior immutable file');
try{$writer->write(['invalid'=>INF]);throw new RuntimeException('Invalid JSON accepted');}catch(JsonException $e){$check($writer->read($newHash)===$new,'Failed write preserves last good aggregate');}
echo "$checks deterministic aggregation/publication checks passed.\n";
