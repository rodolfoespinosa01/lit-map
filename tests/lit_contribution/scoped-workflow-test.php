<?php
/** Truly isolated SQLite + temporary file storage; never writes the demo tables. */
use Drupal\Core\Database\Database;
use Drupal\lit_contribution\Source\ScopedSubmissionSourceInterface;
use Drupal\lit_contribution\Service\{ReviewManager,Publication,AtomicAggregateWriter};
use Drupal\user\Entity\User;
$checks=0;$check=function($ok,$label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$loader=\Drupal::service('class_loader');$loader->addPsr4('Drupal\\sqlite\\',DRUPAL_ROOT.'/core/modules/sqlite/src');
Database::addConnectionInfo('lit_isolated','default',['driver'=>'sqlite','database'=>':memory:','prefix'=>'','namespace'=>'Drupal\\sqlite\\Driver\\Database\\sqlite']);
$db=Database::getConnection('default','lit_isolated');
\Drupal::moduleHandler()->loadInclude('lit_contribution','install');foreach(lit_contribution_schema() as $table=>$definition)$db->schema()->createTable($table,$definition);
$validator=\Drupal::service('lit_contribution.validator');$all=\Drupal::service('lit_contribution.mock_source')->load('SYN_LIT_10000_V1');
$pending=array_values(array_filter($all,fn($s)=>$s['response_id']==='R_LARGE_10003'))[0];
$first=array_values($all)[0];
$source=new class($first,$pending) implements ScopedSubmissionSourceInterface {
 public array $data;public function __construct($a,$b){$this->data=[$a['survey_id'].'/'.$a['response_id']=>$a,$b['survey_id'].'/'.$b['response_id']=>$b];}
 public function surveyId():string{return 'SYN_LIT_10000_V1';}public function listSubmissions():array{return $this->data;}public function getSubmission(string $s,string $r):?array{return $this->data[$s.'/'.$r]??NULL;}
};
$account=\Drupal::currentUser();$original=$account->getAccount();$owner=User::load(\Drupal::service('lit_contribution.owner')->ownerId());$account->setAccount($owner);
$base=sys_get_temp_dir().'/lit-workflow-'.bin2hex(random_bytes(6));mkdir($base.'/web',0700,TRUE);
$review=new ReviewManager($db,$source,$validator,$account,\Drupal::service('private_key'),\Drupal::lock());
$writer=new AtomicAggregateWriter($base.'/web');$pub=new Publication($db,$source,$validator,$review,$writer,\Drupal::service('lit_map.survey_normalizer'),$account);
try{
 $review->sync();$rows=array_values($review->rows());$check(count($rows)===2 && $rows[0]['status']==='pending','Normal import pending');
 $review->decide($rows[0]['id'],'approved','Isolated baseline approval',$rows[0]['fingerprint']);$pub->publish();$before=$pub->publicData()['survey']['totals'];
 // Real contribution administrator role, no entity save required for isolated actor.
 $admin=getenv('LIT_QA_REVIEWER_UID')?User::load((int)getenv('LIT_QA_REVIEWER_UID')):User::create(['uid'=>999999,'name'=>'isolated-reviewer','mail'=>'isolated@example.org','status'=>1,'roles'=>['lit_contribution_administrator']]);$account->setAccount($admin);
 $review->decide($rows[1]['id'],'approved','Isolated pending review',$rows[1]['fingerprint']);$check((int)$review->get($rows[1]['id'])['reviewer_uid']===(int)$admin->id(),'Reviewer identity recorded');
 try{$pub->publish();throw new LogicException('Admin published');}catch(Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e){$check(TRUE,'Admin cannot publish');}
 $check($pub->publicData()['survey']['totals']===$before,'Approval alone does not publish');
 $account->setAccount($owner);$result=$pub->publish();$check($result['included']===2,'Owner publishes approved pending record');$after=$pub->publicData()['survey']['totals'];
 foreach($pending['answers']['languages'] as $l=>$profile){$county=$pending['answers']['county_geoid'];$expected=($before[$county][$l]??0)+(int)($profile['uses_language']==='yes');$check($after[$county][$l]===$expected,'Per-language increment '.$l);}
 $review->sync();$check($review->get($rows[1]['id'])['status']==='approved','Rerun preserves subsequent approval');
 $review->decide($rows[1]['id'],'pending','Reconsider isolated review',$rows[1]['fingerprint']);$review->decide($rows[1]['id'],'rejected','Isolated rejection',$rows[1]['fingerprint']);$review->sync();$check($review->get($rows[1]['id'])['status']==='rejected','Rerun preserves subsequent rejection');$pub->publish();$check($pub->publicData()['survey']['totals']===$before,'Rejection removes contribution on subsequent publication');
 $good=$pub->publicData();$broken=new class($base.'/web') extends AtomicAggregateWriter{public function write(array $d):string{throw new RuntimeException('Injected isolated failure');}};
 $bad=new Publication($db,$source,$validator,$review,$broken,\Drupal::service('lit_map.survey_normalizer'),$account);try{$bad->publish();throw new LogicException('No write failure');}catch(RuntimeException $e){$check($pub->publicData()===$good,'Failure preserves last good isolated publication');}
 echo "$checks isolated workflow checks passed. Live source/review/publication untouched.\n";
}finally{$account->setAccount($original);Database::closeConnection('default','lit_isolated');}
