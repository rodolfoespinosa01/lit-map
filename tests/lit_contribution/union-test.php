<?php
require __DIR__.'/../../web/modules/custom/lit_contribution/src/Service/AggregateBuilder.php';
require __DIR__.'/../../web/modules/custom/lit_map/src/Service/SurveyNormalizer.php';
require __DIR__.'/../../web/modules/custom/lit_contribution/src/Service/SubmissionValidator.php';
use Drupal\lit_contribution\Service\{AggregateBuilder,SubmissionValidator};
use Drupal\lit_map\Service\SurveyNormalizer;
$v=new SubmissionValidator(realpath(__DIR__.'/../../web'));$normalizer=new SurveyNormalizer();$source=[];$rows=[];$id=0;
// English 100, Spanish 60, overlap 40 -> ANY 120. Korean raises ANY to 130.
// Two additional eligible all-no submissions must not count toward ANY.
foreach([[60,['en']],[20,['es']],[30,['en','es']],[10,['en','es','ko']],[10,['ko']],[2,[]]] as [$n,$ls])for($j=0;$j<$n;$j++){
 $id++;$profiles=[];foreach(['en','es','ko','ar'] as $l)$profiles[$l]=['uses_language'=>in_array($l,$ls,TRUE)?'yes':'no'];
 $town=(($ls===['en'] && $j<10)||($ls===['es']&&$j<5))?NULL:'m3400300700';
 $source['fixture/'.$id]=['answers'=>['county_geoid'=>'34003','municipality'=>$town,'languages'=>$profiles,'aggregate_permission'=>'yes']];
 $rows[]=['id'=>$id,'survey_id'=>'fixture','response_id'=>(string)$id,'status'=>'approved','duplicate_conflict'=>0,'duplicate_choice'=>0];
}
$result=AggregateBuilder::build($source,$rows);$aggregate=$result['aggregate'];$public=$normalizer->normalize($aggregate,$v->counties(),$v->references());
$checks=0;$check=function($ok,$label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$union=function(array $selected,?string $town=NULL)use($public){$total=0;foreach($public['combinations'] as $g)if(($town===NULL||$g['municipality']===$town)&&array_intersect($g['languages'],$selected))$total+=$g['count'];return $total;};
$check($public['schemaVersion']===3,'Schema version');$check(count($result['included'])===132,'Eligible count');
$check($union(['en'])===100,'English');$check($union(['es'])===60,'Spanish');$check($union(['en','es'])===120,'Overlap counted once');$check($union(['en','es','ko'])===130,'Three-way union');
$check($union(['en','es'],'m3400300700')===105 && $union(['en','es'],'_unspecified')===15,'Town plus unspecified');
$check($public['totals']['34003']['ar']===0 && !isset($public['totals']['34003']['hi']),'Zero versus missing');
$check(array_sum(array_column($public['combinations'],'count'))===132,'Empty language groups retained');
$reject=function($bad,$label)use($normalizer,$v,$check){try{$normalizer->normalize($bad,$v->counties(),$v->references());}catch(InvalidArgumentException $e){$check(TRUE,$label);return;}throw new RuntimeException('Accepted '.$label);};
$bad=$aggregate;$bad['groups'][]=$bad['groups'][0];$reject($bad,'duplicate group');
$bad=$aggregate;$bad['groups'][0]['response_count']=-1;$reject($bad,'negative');
$bad=$aggregate;$bad['groups'][0]['response_count']=1.5;$reject($bad,'fraction');
$bad=$aggregate;$bad['groups'][0]['county_geoid']='34017';$reject($bad,'mismatch/marginal inconsistency');
$bad=$aggregate;$bad['groups'][0]['languages']=['xx'];$reject($bad,'unknown language');
$bad=$aggregate;$bad['groups'][0]['languages']=['es','en'];$reject($bad,'noncanonical order');
$bad=$aggregate;$bad['groups'][0]['languages']=['en','en'];$reject($bad,'duplicate language');
$bad=$aggregate;$bad['groups'][0]['response_count']++;$reject($bad,'inconsistent marginal totals');
$bad=$aggregate;$bad['groups'][0]['email']='private@example.org';$reject($bad,'private extra field');
$bad=$aggregate;unset($bad['groups']);$reject($bad,'missing groups');
$bad=$aggregate;$bad['groups'][0]['response_count']=PHP_INT_MAX;$reject($bad,'unsafe integer');
echo "$checks deterministic union/validation checks passed. 100 + 60 with overlap 40 = 120, not 160.\n";
// Optional public DTO for shared JS tests, outside the public root and ignored.
if(in_array('--export',$argv??[],TRUE)){ $dir=__DIR__.'/../../private/union-qa';if(!is_dir($dir))mkdir($dir,0700,TRUE);file_put_contents($dir.'/deterministic.json',json_encode($public,JSON_THROW_ON_ERROR));}
