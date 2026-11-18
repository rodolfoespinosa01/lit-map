<?php
/** Explicit local demo preapproval, once per distinct dataset. Never a normal import. */
use Drupal\user\Entity\User;
use Drupal\lit_contribution\Source\MockQualtricsSource;
if(getenv('IS_DDEV_PROJECT')!=='true')throw new RuntimeException('Local DDEV setup only.');
$db=\Drupal::database();$scope=MockQualtricsSource::LARGE;
$owner=\Drupal::service('lit_contribution.owner')->ownerId();if(!$owner)throw new RuntimeException('Bootstrap the application owner first.');
$account=\Drupal::service('account_switcher');$account->switchTo(User::load($owner));
try{
 // Retain the prior publication pointer in its own private dataset history.
 $priorScope=\Drupal::state()->get('lit_contribution.active_dataset',MockQualtricsSource::LEGACY);
 if($priorScope!==$scope){
   $prior=$db->select('lit_contribution_publication','p')->fields('p')->condition('id',1)->execute()->fetchAssoc();
   if($prior)$db->merge('lit_contribution_dataset')->keys(['survey_id'=>$priorScope])->fields(['file_hash'=>$prior['file_hash'],'published_at'=>$prior['published_at'],'publisher_uid'=>$prior['publisher_uid']])->execute();
 }
 \Drupal::state()->set('lit_contribution.active_dataset',$scope);
 $mock=\Drupal::service('lit_contribution.mock_source');
 $reviews=new \Drupal\lit_contribution\Service\ReviewManager($db,$mock,\Drupal::service('lit_contribution.validator'),\Drupal::currentUser(),\Drupal::service('private_key'),\Drupal::lock());
 $counts=$reviews->sync();echo json_encode($counts).PHP_EOL;
 $seeded=$db->select('lit_contribution_dataset','d')->fields('d',['seeded'])->condition('survey_id',$scope)->execute()->fetchField();
 if($seeded){echo 'Dataset already initialized. Decisions preserved; no automatic reapproval or republication. Publish explicitly as owner if desired.'.PHP_EOL;return;}
 $source=$mock->listSubmissions();
 $reviews->locked(function()use($db,$scope,$source,$owner){
   $tx=$db->startTransaction();
   try{
     $rows=$db->select('lit_contribution_review','r')->fields('r')->condition('survey_id',$scope)->execute()->fetchAll(PDO::FETCH_ASSOC);$approved=0;
     foreach($rows as $r){
       $s=$source[$r['survey_id'].'/'.$r['response_id']];
       if($r['response_id']>'R_LARGE_10000' || $r['status']!=='pending' || $r['reviewed_at'] || $r['duplicate_conflict'] || $s['answers']['aggregate_permission']!=='yes')continue;
       $db->update('lit_contribution_review')->fields(['status'=>'approved','reviewer_uid'=>$owner,'reviewed_at'=>time(),'reason'=>'Explicit local 10,000-response synthetic demo preapproval; not a research review.'])->condition('id',$r['id'])->execute();
       $db->insert('lit_contribution_audit')->fields(['review_id'=>$r['id'],'action'=>'demo_preapproved','actor_uid'=>$owner,'occurred_at'=>time(),'reason'=>'Explicit one-time local demo setup.'])->execute();$approved++;
     }
     $db->merge('lit_contribution_dataset')->keys(['survey_id'=>$scope])->fields(['seeded'=>1])->execute();unset($tx);
     echo 'Explicit setup preapproved '.$approved.' new/unreviewed synthetic candidates.'.PHP_EOL;
   }catch(Throwable $e){$tx->rollBack();throw $e;}
 });
 echo json_encode(\Drupal::service('lit_contribution.publication')->publish()).PHP_EOL;
}finally{$account->switchBack();}
