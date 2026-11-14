<?php
use Drupal\user\Entity\User;
$account=\Drupal::service('account_switcher');$account->switchTo(User::load(\Drupal::service('lit_contribution.owner')->ownerId()));
try{
 $r=\Drupal::service('lit_contribution.publication')->reconcile();
 $check=function($ok,$msg){if(!$ok)throw new RuntimeException($msg);};
 $check($r['matches'],'Publication not reconciled');$check($r['counts']['approved_eligible']===10000,'Approved count');$check($r['counts']['pending']===100,'Pending count');
 $expected=['34013'=>2200,'34017'=>1800,'34003'=>1600,'34027'=>1200,'34039'=>1200,'34023'=>1400];
 foreach($r['countyRecords'] as $county=>$n)$check($n===($expected[$county]??40),'County '.$county);
 $check(count($r['countyRecords'])===21,'All counties covered');
 $refs=\Drupal::service('lit_contribution.validator')->references();$nb=array_values(array_filter($refs['municipalities'],fn($t)=>$t['name']==='New Brunswick city'))[0]['id'];
 $check($r['municipalityRecords'][$nb]===700,'New Brunswick');
 $source=\Drupal::service('lit_contribution.source')->listSubmissions();$now=time();$emails=[];
 foreach($source as $s){$stamp=strtotime($s['submitted_at']);$check($stamp<=$now && $stamp>=$now-366*86400,'Timestamp window');if($s['response_id']<='R_LARGE_10000'){$check(!isset($emails[$s['email']]),'Unique baseline email');$emails[$s['email']]=TRUE;}}
 $public=\Drupal::service('lit_contribution.publication')->publicData();foreach($r['totals'] as $key=>$n){[$c,$l]=explode('/',$key);$check($public['survey']['totals'][$c][$l]===$n,'Public total');}
 $legacy=\Drupal::database()->select('lit_contribution_review','r')->fields('r')->condition('survey_id','SYN_LIT_PROVISIONAL_V1')->execute()->fetchAll(PDO::FETCH_ASSOC);
 $check(count($legacy)===64 && count(array_filter($legacy,fn($r)=>$r['status']==='approved'))===8,'Legacy decisions preserved');
 echo json_encode(['dataset'=>$r['dataset'],'counts'=>$r['counts'],'counties'=>$r['countyRecords'],'new_brunswick'=>$r['municipalityRecords'][$nb],'publication'=>$r['publication'],'public_en'=>$public['survey']['totals'],'legacy_records'=>count($legacy)],JSON_PRETTY_PRINT).PHP_EOL;
}finally{$account->switchBack();}
