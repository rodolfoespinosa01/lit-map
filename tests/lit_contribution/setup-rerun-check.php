<?php
$db=\Drupal::database();
$snapshot=function()use($db){
 $rows=$db->select('lit_contribution_review','r')->fields('r')->orderBy('id')->execute()->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$r)unset($r['synced_at']);unset($r);
 return hash('sha256',json_encode([$rows,$db->select('lit_contribution_audit','a')->fields('a')->orderBy('id')->execute()->fetchAll(PDO::FETCH_ASSOC),$db->select('lit_contribution_publication','p')->fields('p')->execute()->fetchAll(PDO::FETCH_ASSOC)]));
};$before=$snapshot();require dirname(__DIR__,2).'/scripts/setup-contribution-large-demo.php';
if($snapshot()!==$before)throw new RuntimeException('Rerun changed review decisions or publication');
echo "Setup rerun preserved source references, decisions, audit and publication (only sync-check timestamps may change).\n";
// Reconstruct the previous task's legacy metadata snapshot using its archived pointer.
$rows=$db->select('lit_contribution_review','r')->fields('r')->condition('survey_id','SYN_LIT_PROVISIONAL_V1')->orderBy('id')->execute()->fetchAll(PDO::FETCH_ASSOC);
$ids=array_column($rows,'id');$audit=$db->select('lit_contribution_audit','a')->fields('a')->condition('review_id',$ids,'IN')->orderBy('id')->execute()->fetchAll(PDO::FETCH_ASSOC);
$p=$db->select('lit_contribution_dataset','d')->fields('d')->condition('survey_id','SYN_LIT_PROVISIONAL_V1')->execute()->fetchAssoc();
$pointer=['id'=>'1','file_hash'=>$p['file_hash'],'published_at'=>$p['published_at'],'publisher_uid'=>$p['publisher_uid']];
$hash=hash('sha256',json_encode(['review'=>$rows,'audit'=>$audit,'publication'=>[$pointer]]));
if($hash!=='76c4264162be8a4ceb0f76ed112693895711a9458881a5c674bc686154277cbf')throw new RuntimeException('Legacy metadata fingerprint changed');
echo "Legacy 64-record decisions/audit and archived publication match the exact previous fingerprint.\n";
