<?php
/** Run: ddev drush php:script tests/lit_contribution/integration.php. Rolls back metadata. */
use Drupal\lit_contribution\Source\SubmissionSourceInterface;
use Drupal\lit_contribution\Service\{SubmissionValidator,ReviewManager,Publication,AtomicAggregateWriter,Invitation};
use Drupal\Core\Session\UserSession;

$GLOBALS['lit_contribution_checks']=[];
function verify($ok,$label){if(!$ok)throw new RuntimeException($label);$GLOBALS['lit_contribution_checks'][]=$label;}
function denied(callable $call,$label){try{$call();}catch(Throwable $e){verify(TRUE,$label);return;}throw new RuntimeException('Expected failure: '.$label);}
$v=\Drupal::service('lit_contribution.validator');
$fixture=json_decode(file_get_contents(dirname(DRUPAL_ROOT).'/fixtures/lit-contribution/submissions.v1.json'),TRUE,512,JSON_THROW_ON_ERROR);
final class TestSource implements SubmissionSourceInterface {
 public array $rows=[];
 public function __construct(private SubmissionValidator $validator,array $rows){$this->rows=$rows;}
 public function listSubmissions():array{$r=[];foreach($this->rows as $s){$s=$this->validator->normalize($s);$r[$s['survey_id'].'/'.$s['response_id']]=$s;}return $r;}
 public function getSubmission(string $surveyId,string $responseId):?array{return $this->listSubmissions()[$surveyId.'/'.$responseId]??NULL;}
}
$source=new TestSource($v,$fixture['submissions']);$db=\Drupal::database();$account=\Drupal::currentUser();$original=$account->getAccount();
$review=new ReviewManager($db,$source,$v,$account,\Drupal::service('private_key'),\Drupal::lock());
$writer=\Drupal::service('lit_contribution.writer');$normalizer=\Drupal::service('lit_map.survey_normalizer');
$pub=new Publication($db,$source,$v,$review,$writer,$normalizer,$account);
$transaction=$db->startTransaction();
try {
 $account->setAccount(new UserSession());denied(fn()=>$review->listing(),'Anonymous service access denied');denied(fn()=>$review->sync(),'Anonymous synchronization denied');denied(fn()=>$pub->publish(),'Anonymous publication denied');
 $account->setAccount(\Drupal\user\Entity\User::load(1));
 // Tests use separate source IDs to avoid overwriting any existing work.
 foreach($source->rows as &$s)$s['survey_id']='QA_TRANSACTION_V1';unset($s);
 $result=$review->sync();verify($result['new']===64,'New snapshot creates 64 pending metadata rows');
 $rows=$review->listing();$byResponse=array_column(array_values($rows),NULL,'response_id');
 $id=(int)$byResponse['R_DEMO_001']['id'];
 verify($review->get($id)['status']==='pending','Default pending');
 verify($review->get($id)['submitted_at']==='2026-11-10T09:00:00-05:00','Source submitted timestamp retains explicit offset');
 verify($review->get($id)['imported_at']>0 && !$review->get($id)['reviewed_at'] && !$review->get($id)['published_at'],'Imported, reviewed and published times distinct');
 verify($byResponse['R_DEMO_061']['duplicate_conflict'] && $byResponse['R_DEMO_062']['duplicate_conflict'],'Trimmed case-normalized email conflict flagged');
 verify(!$byResponse['R_DEMO_063']['duplicate_conflict'] && !$byResponse['R_DEMO_064']['duplicate_conflict'],'Plus and dot variants remain distinct');
 verify(!str_contains(json_encode($rows),'@example.') && !array_key_exists('answers',$review->get($id)),'Database stores no plaintext email or raw answers');
 $hash=$review->get($id)['fingerprint'];$imported=$review->get($id)['imported_at'];
 $token=$review->reviewToken($id);
 $review->decide($id,'approved','Synthetic integration review',$hash);
 denied(fn()=>$review->verifyReviewToken($id,$token),'Signed stale form version rejected after a decision');
 denied(fn()=>$review->decide($id,'pending','Stale concurrent form',$hash,$token),'Signed version rechecked inside mutation lock');
 verify($review->get($id)['reviewer_uid']==1 && $review->get($id)['reviewed_at']>0,'Reviewer identity and reviewed timestamp recorded');
 $again=$review->sync();verify($again['new']===0 && $again['unchanged']===64 && $review->get($id)['status']==='approved' && $review->get($id)['imported_at']===$imported,'Idempotent import preserves review and first import time');
 $source->rows[0]['updated_at']='2026-11-12T09:00:00-05:00';$review->sync();verify($review->get($id)['status']==='approved','Bookkeeping-only timestamp change does not invalidate answers');
 foreach([1,2,3,4,9] as $n){$rid=(int)$byResponse[sprintf('R_DEMO_%03d',$n+1)]['id'];$review->decide($rid,'approved','Synthetic approval',$review->get($rid)['fingerprint']);}
 $result=$pub->publish();verify($result['included']===5,'Publication excludes approved submission without permission');
 $public=$pub->publicData();$expected=[];
 foreach(array_slice($source->rows,0,5) as $s)foreach($s['answers']['languages'] as $l=>$profile)$expected[$l]=($expected[$l]??0)+(int)($profile['uses_language']==='yes');
 foreach($expected as $l=>$total)verify($public['survey']['totals']['34003'][$l]===$total,'Independent approved total '.$l);
 verify($public['survey']['breakdown']['34003']['_unspecified']['zh']===0,'Explicit no answer is a zero, not absent');
 verify(!isset($public['survey']['totals']['34001']),'Unobserved place remains missing');
 $serialized=json_encode($public);verify(!preg_match('/@example|R_DEMO|QA_TRANSACTION|email|reviewer|submitted_at|T09:/',$serialized),'Public payload contains no private identifiers or exact timestamps');
 verify(preg_match('/^\d{4}-\d\d-\d\d$/D',$public['date'])===1,'Public date has day precision only');
 verify($review->get($id)['published_at']>0 && $review->get($id)['in_publication'],'Publication inclusion timestamp recorded separately');
 $old=$public;$oldHash=$db->select('lit_contribution_publication','p')->fields('p',['file_hash'])->condition('id',1)->execute()->fetchField();
 $broken=new class(DRUPAL_ROOT) extends AtomicAggregateWriter { public function write(array $document):string{throw new RuntimeException('Injected disk failure');} };
 $badPublisher=new Publication($db,$source,$v,$review,$broken,$normalizer,$account);
 denied(fn()=>$badPublisher->publish(),'Injected atomic file failure rejected');verify($pub->publicData()===$old,'Last good publication survives writer failure');
 $source->rows[0]['answers']['languages']['en']['speaking']='none';
 denied(fn()=>$review->decide($id,'pending','Old form',$hash),'Stale review fingerprint denied before synchronization');
 denied(fn()=>$pub->publish(),'Unsynchronized changed response cannot publish');verify($pub->publicData()===$old,'Last good snapshot survives changed-source failure');
 $review->sync();verify($review->get($id)['status']==='pending' && !$review->get($id)['reviewed_at'],'Materially changed answer needs re-review');
 $pub->publish();verify(!$review->get($id)['in_publication'],'Pending changed answer removed on next publication');
 $rid=(int)$byResponse['R_DEMO_002']['id'];$review->decide($rid,'pending','Reconsider',$review->get($rid)['fingerprint']);$review->decide($rid,'rejected','Rejected demonstration',$review->get($rid)['fingerprint']);$pub->publish();verify(!$review->get($rid)['in_publication'],'Rejection removes previously approved contribution on next publication');
 unset($source->rows[2]);$review->sync();$pub->publish();$rid=(int)$byResponse['R_DEMO_003']['id'];verify(!$review->get($rid)['source_present'] && !$review->get($rid)['in_publication'],'Confirmed source removal excluded');
 $duplicate=(int)$byResponse['R_DEMO_061']['id'];denied(fn()=>$review->decide($duplicate,'approved','Conflict',$review->get($duplicate)['fingerprint']),'Unresolved duplicates cannot be approved');
 $review->resolve($duplicate,'Keep this fictional response; other source reference excluded.',$review->get($duplicate)['fingerprint']);
 verify(!$review->get($duplicate)['duplicate_conflict'] && $review->get($duplicate)['status']==='pending','Resolution leaves chosen duplicate pending for explicit approval');
 $review->decide($duplicate,'approved','Chosen synthetic duplicate',$review->get($duplicate)['fingerprint']);$review->sync();verify($review->get($duplicate)['status']==='approved','Idempotent sync preserves duplicate resolution');
 $pub->publish();verify($review->get($duplicate)['in_publication'] && !$review->get((int)$byResponse['R_DEMO_062']['id'])['in_publication'],'Only chosen approved duplicate contributes');
 $source->rows[61]['answers']['languages']['en']['home_use']='prefer_not_to_answer';$review->sync();verify($review->get($duplicate)['duplicate_conflict'] && $review->get($duplicate)['status']==='pending','Changed duplicate invalidates group resolution');
 $separate=$source->rows[60];$separate['survey_id']='QA_OTHER_SURVEY';$source->rows[]=$separate;$review->sync();
 $other=array_values(array_filter($review->listing(),fn($r)=>$r['survey_id']==='QA_OTHER_SURVEY'))[0];verify(!$other['duplicate_conflict'],'Duplicate matching scoped to survey');
 $case=$source->rows[0];$case['response_id']='r_demo_001';$case['email']='case-sensitive@example.org';$source->rows[]=$case;$review->sync();
 verify(count(array_filter($review->listing(),fn($r)=>$r['survey_id']==='QA_TRANSACTION_V1' && in_array($r['response_id'],['R_DEMO_001','r_demo_001'],TRUE)))===2,'Source identity unique key is case-sensitive');
 $source->rows[0]['answers']['county_geoid']='99999';$before=$review->listing();denied(fn()=>$review->sync(),'Invalid source snapshot rejected atomically');verify($review->listing()===$before,'Invalid sync leaves metadata untouched');
 foreach(['https://rutgers.qualtrics.com/jfe/form/SV_Demo123'] as $url)verify(Invitation::validUrl($url),'Valid configured survey URL accepted');
 foreach(['http://rutgers.qualtrics.com/jfe/form/SV_Demo','https://qualtrics.com.evil.test/jfe/form/SV_Demo','https://user:pass@rutgers.qualtrics.com/jfe/form/SV_Demo','javascript:alert(1)','https://example.org/survey','https://rutgers.qualtrics.com/jfe/form/SV_Demo?email=x'] as $url)verify(!Invitation::validUrl($url),'Unsafe/non-survey invitation URL rejected');
 verify(count($review->auditTrail($id))>=3,'Review/import audit history retained');
} finally { $transaction->rollBack();$account->setAccount($original); }
$checks=$GLOBALS['lit_contribution_checks'];
echo count($checks)." contribution integration checks passed; database changes rolled back.\n";
file_put_contents(dirname(DRUPAL_ROOT).'/docs/contribution/integration-results.json',json_encode(['checks'=>$checks],JSON_PRETTY_PRINT)."\n");
