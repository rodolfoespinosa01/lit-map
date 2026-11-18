<?php
/** Explicit, local-only baseline activation. Old source and review decisions retained. */
use Drupal\lit_contribution\Source\MockQualtricsSource;
use Drupal\lit_contribution\Service\ReviewManager;
if(getenv('IS_DDEV_PROJECT')!=='true' || getenv('LIT_CONFIRM_BASELINE_ACTIVATION')!=='yes')throw new RuntimeException('Use explicit local baseline activation confirmation.');
$db=\Drupal::database();$new=MockQualtricsSource::QUESTIONNAIRE;$old=\Drupal::state()->get('lit_contribution.baseline',MockQualtricsSource::LARGE);$active=\Drupal::state()->get('lit_contribution.active_dataset',MockQualtricsSource::LARGE);
$account=\Drupal::service('account_switcher');$account->switchTo(\Drupal\user\Entity\User::load(\Drupal::service('lit_contribution.owner')->ownerId()));
try{
  $mock=\Drupal::service('lit_contribution.mock_source');$snapshot=$mock->load($new);if(count($snapshot)!==10000)throw new RuntimeException('Expected exactly 10,000 new baseline responses.');foreach($snapshot as $s)if($s['answers']['aggregate_permission']!=='yes')throw new RuntimeException('Resolve generated baseline references before activation.');
  \Drupal::state()->set('lit_contribution.active_dataset',$new);
  $reviews=new ReviewManager($db,$mock,\Drupal::service('lit_contribution.validator'),\Drupal::currentUser(),\Drupal::service('private_key'),\Drupal::lock());
  $seeded=$db->select('lit_contribution_dataset','d')->fields('d',['seeded'])->condition('survey_id',$new)->execute()->fetchField();
  if(!$seeded){$reviews->sync();$reviews->locked(function()use($db,$new){$tx=$db->startTransaction();try{foreach($db->select('lit_contribution_review','r')->fields('r')->condition('survey_id',$new)->execute()->fetchAll(PDO::FETCH_ASSOC) as $r){if($r['status']!=='pending' || $r['reviewed_at'])continue;$db->update('lit_contribution_review')->fields(['status'=>'approved','reviewed_at'=>time(),'reviewer_uid'=>(int)\Drupal::currentUser()->id(),'reason'=>'Explicit synthetic questionnaire baseline activation; not a research review.'])->condition('id',$r['id'])->execute();$db->insert('lit_contribution_audit')->fields(['review_id'=>$r['id'],'action'=>'questionnaire_demo_preapproved','actor_uid'=>(int)\Drupal::currentUser()->id(),'occurred_at'=>time(),'reason'=>'Explicit local synthetic baseline setup.'])->execute();}$db->merge('lit_contribution_dataset')->keys(['survey_id'=>$new])->fields(['seeded'=>1])->execute();unset($tx);}catch(Throwable $e){$tx->rollBack();throw $e;}});}
  \Drupal::state()->set('lit_contribution.baseline',$new);
  try{$result=\Drupal::service('lit_contribution.publication')->publish();echo json_encode($result).PHP_EOL;}catch(Throwable $e){\Drupal::state()->set('lit_contribution.baseline',$old);throw $e;}
  echo "Questionnaire baseline active; previous baseline records and decisions retained, never combined.\n";
}finally{\Drupal::state()->set('lit_contribution.active_dataset',$active);$account->switchBack();}
