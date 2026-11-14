<?php
/** Read-only independent source-to-public combination reconciliation. */
$account=\Drupal::service('account_switcher');$account->switchTo(\Drupal\user\Entity\User::load(\Drupal::service('lit_contribution.owner')->ownerId()));
try{
 $source=\Drupal::service('lit_contribution.source')->listSubmissions();$rows=\Drupal::service('lit_contribution.reviews')->rows();
 $public=\Drupal::service('lit_contribution.publication')->publicData()['survey'];
 if(!in_array($public['schemaVersion'],[3,4],TRUE))throw new RuntimeException('Wrong schema');
 $eligible=[];foreach($rows as $r){$s=$source[$r['survey_id'].'/'.$r['response_id']]??NULL;if($s && $r['status']==='approved' && !$r['duplicate_conflict'] && (!$r['duplicate_choice']||(int)$r['duplicate_choice']===(int)$r['id']) && $s['answers']['aggregate_permission']==='yes')$eligible[]=$s;}
 if(count($eligible)!==10000)throw new RuntimeException('Eligible baseline changed');
 $sets=[['en'],['es'],['en','es'],['en','es','ko'],array_column($public['languages'],'id')];$expected=[];
 foreach($sets as $selected){
   $totals=[];$breakdown=[];
   foreach($eligible as $s){$a=$s['answers'];$c=$a['county_geoid'];$m=$a['municipality']??'_unspecified';
     // Establish explicit zero only where a selected language was answered.
     $known=FALSE;$match=FALSE;foreach($selected as $l){$p=$a['languages'][$l]['uses_language']??NULL;if($p!==NULL && $p!=='prefer_not_to_answer')$known=TRUE;if($p==='yes')$match=TRUE;}
     if($known){$totals[$c]=($totals[$c]??0)+(int)$match;$breakdown[$c][$m]=($breakdown[$c][$m]??0)+(int)$match;}
   }
   $actual=[];$actualTowns=[];foreach($public['breakdown'] as $c=>$areas)foreach($areas as $m=>$values)if(array_intersect($selected,array_keys($values))){$actual[$c]??=0;$actualTowns[$c][$m]=0;}foreach($public['combinations'] as $g){$match=(bool)array_intersect($selected,$g['languages']);$c=$g['county'];$m=$g['municipality'];if(!$match)continue;$actual[$c]=($actual[$c]??0)+($match?$g['count']:0);$actualTowns[$c][$m]=($actualTowns[$c][$m]??0)+($match?$g['count']:0);}
   ksort($totals);ksort($actual);ksort($breakdown);ksort($actualTowns);foreach($breakdown as &$v)ksort($v);unset($v);foreach($actualTowns as &$v)ksort($v);unset($v);
   if($totals!==$actual || $breakdown!==$actualTowns)throw new RuntimeException('Source/group union mismatch');
   $expected[]=['languages'=>$selected,'total'=>array_sum($totals),'totals'=>$totals,'breakdown'=>$breakdown];
 }
 $last=end($expected);if($last['total']!==10000)throw new RuntimeException('Full language set does not reconcile');
 $directory=dirname(DRUPAL_ROOT).'/private/union-qa';if(!is_dir($directory))mkdir($directory,0700,TRUE);
 file_put_contents($directory.'/source-expected.json',json_encode($expected,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
 $nb=array_values(array_filter($public['municipalities'],fn($m)=>$m['name']==='New Brunswick city'))[0]['id'];
 echo json_encode(['schema'=>$public['schemaVersion'],'eligible'=>count($eligible),'groups'=>count($public['combinations']),'sets'=>array_map(fn($e)=>['languages'=>$e['languages'],'all_counties'=>$e['total'],'Bergen'=>$e['totals']['34003'],'Middlesex'=>$e['totals']['34023'],'New_Brunswick'=>$e['breakdown']['34023'][$nb]],$expected)],JSON_PRETTY_PRINT).PHP_EOL;
}finally{$account->switchBack();}
