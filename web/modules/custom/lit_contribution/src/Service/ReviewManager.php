<?php
namespace Drupal\lit_contribution\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\lit_contribution\Source\SubmissionSourceInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Drupal metadata repository; answers stay in the source adapter. */
final class ReviewManager {
  public const LOCK = 'lit_contribution.mutation';
  public function __construct(private readonly Connection $db, private readonly SubmissionSourceInterface $source,
    private readonly SubmissionValidator $validator, private readonly AccountProxyInterface $account,
    private readonly PrivateKey $key, private readonly LockBackendInterface $lock) {}
  public function requirePermission(string $permission): void {
    if(in_array($permission,['publish lit contributions','synchronize lit contributions','administer lit contribution settings'],TRUE) && \Drupal::state()->get('lit_contribution.owner_uid'))\Drupal::service('lit_contribution.owner')->requireOwner();
    if (!$this->account->isAuthenticated() || !$this->account->hasPermission($permission)) throw new AccessDeniedHttpException();
  }
  public function locked(callable $callback): mixed {
    if (!$this->lock->acquire(self::LOCK, 120)) throw new \RuntimeException('Another contribution operation is running. Try again.');
    try { return $callback(); } finally { $this->lock->release(self::LOCK); }
  }
  public function scope(): ?string { return $this->source instanceof \Drupal\lit_contribution\Source\ScopedSubmissionSourceInterface ? $this->source->surveyId() : NULL; }
  public function rows(): array {
    $q=$this->db->select('lit_contribution_review','r')->fields('r')->orderBy('id');
    if($this->scope())$q->condition('survey_id',$this->scope());
    return array_filter($q->execute()->fetchAllAssoc('id',\PDO::FETCH_ASSOC),fn($r)=>!($this->source instanceof \Drupal\lit_contribution\Source\PublishedBaselineAndExports) || $r['origin']==='upload' || \Drupal\lit_contribution\Source\PublishedBaselineAndExports::baseline($r['survey_id'],$r['response_id']));
  }
  private function all(): array { return $this->rows(); }
  public function listing(bool $pendingOnly=FALSE): array {
    $this->requirePermission('review lit contributions');
    $q=$this->db->select('lit_contribution_review','r')->fields('r');if($this->scope())$q->condition('survey_id',$this->scope());
    if($this->source instanceof \Drupal\lit_contribution\Source\PublishedBaselineAndExports){$q->condition('origin','upload');if($pendingOnly)$q->condition('publication_permission',1)->condition('duplicate_conflict',0);}
    try{$dates=SubmissionDates::bounds((string)\Drupal::request()->query->get('submitted_from',''),(string)\Drupal::request()->query->get('submitted_through',''));if($dates['from'])$q->condition('submitted_epoch',$dates['from']->getTimestamp(),'>=');if($dates['until'])$q->condition('submitted_epoch',0,'>')->condition('submitted_epoch',$dates['until']->getTimestamp(),'<');}catch(\Throwable $e){return [];}
    if($pendingOnly)$q->condition('status','pending');
    return $q->orderBy('status','DESC')->orderBy('id')->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(50)->execute()->fetchAllAssoc('id',\PDO::FETCH_ASSOC);
  }
  public function get(int $id): array {
    $this->requirePermission('review lit contributions');
    $row = $this->db->select('lit_contribution_review', 'r')->fields('r')->condition('id', $id)->execute()->fetchAssoc();
    if (!$row) throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    if($this->source instanceof \Drupal\lit_contribution\Source\PublishedBaselineAndExports && $row['origin']!=='upload' && !\Drupal::service('lit_contribution.owner')->isOwner())throw new AccessDeniedHttpException();
    return $row;
  }
  private function tokenForRow(array $r): string {
    return hash_hmac('sha256',implode('|',[$r['id'],$r['fingerprint'],$r['status'],$r['duplicate_conflict'],$r['duplicate_choice'],$r['reviewed_at'],$this->account->id()]),$this->key->get());
  }
  public function reviewToken(int $id): string { return $this->tokenForRow($this->get($id)); }
  public function verifyReviewToken(int $id, string $token): string {
    $row=$this->get($id);
    if (!hash_equals($this->tokenForRow($row),$token)) throw new \RuntimeException('Review version changed. Reload the response.');
    return $row['fingerprint'];
  }
  public function submission(int $id): ?array {
    $r = $this->get($id); return $this->source->getSubmission($r['survey_id'], $r['response_id']);
  }
  public function auditTrail(int $id): array {
    $this->get($id);
    return $this->db->select('lit_contribution_audit', 'a')->fields('a')->condition('review_id', $id)->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
  private function audit(int $id, string $action, string $reason): void {
    $this->db->insert('lit_contribution_audit')->fields(['review_id'=>$id, 'action'=>$action, 'actor_uid'=>(int)$this->account->id(), 'occurred_at'=>time(), 'reason'=>$reason])->execute();
  }
  private function update(int $id, array $fields): void { $this->db->update('lit_contribution_review')->fields($fields)->condition('id', $id)->execute(); }
  public function sync(): array {
    $this->requirePermission('synchronize lit contributions');
    if($this->source instanceof \Drupal\lit_contribution\Source\PublishedBaselineAndExports)throw new \RuntimeException('Use Import survey responses; operational uploads are cumulative and are never synchronized as deletions.');
    return $this->locked(function () {
      // Fully validate the complete snapshot before touching any metadata.
      $snapshot = $this->source->listSubmissions();
      $tx = $this->db->startTransaction();
      try {
        $existing=[]; foreach ($this->all() as $r) $existing[$r['survey_id'].'/'.$r['response_id']]=$r;
        $counts=['new'=>0,'changed'=>0,'removed'=>0,'unchanged'=>0]; $dirtyGroups=[];
        foreach ($snapshot as $key=>$s) {
          $digest=hash_hmac('sha256', $s['survey_id'].'\0'.$s['email'], $this->key->get());
          $hash=$this->validator->fingerprint($s); $old=$existing[$key]??NULL;
          $fields=['fingerprint'=>$hash,'email_digest'=>$digest,'source_present'=>1,'submitted_at'=>$s['submitted_at'],'submitted_day'=>SubmissionDates::day($s['submitted_at'])??'','submitted_epoch'=>strtotime($s['submitted_at'])?:0,'source_updated_at'=>$s['updated_at'],'synced_at'=>time()];
          if (!$old || $old['fingerprint']!==$hash || !$old['source_present']) {
            $fields+=['status'=>'pending','reviewer_uid'=>0,'reviewed_at'=>0,'reason'=>'Awaiting review of current source response.','duplicate_choice'=>0];
            $dirtyGroups[$s['survey_id'].'/'.$digest]=TRUE;
            if ($old) $dirtyGroups[$old['survey_id'].'/'.$old['email_digest']]=TRUE;
          }
          if (!$old) {
            $id=(int)$this->db->insert('lit_contribution_review')->fields($fields+['survey_id'=>$s['survey_id'],'response_id'=>$s['response_id'],'imported_at'=>time()])->execute();
            $this->audit($id,'imported','New mock source reference; pending.');$counts['new']++;
          } else {
            $this->update((int)$old['id'],$fields);
            if ($old['fingerprint']!==$hash || !$old['source_present']) {$counts['changed']++;$this->audit((int)$old['id'],'changed','Source changed; prior decision invalidated.');}
            else $counts['unchanged']++;
          }
        }
        foreach ($existing as $key=>$old) if (!isset($snapshot[$key]) && $old['source_present']) {
          $this->update((int)$old['id'],['source_present'=>0,'status'=>'pending','reviewed_at'=>0,'reviewer_uid'=>0,'duplicate_choice'=>0,'duplicate_conflict'=>0,'synced_at'=>time(),'reason'=>'Source response removed; excluded on next publication.']);
          $dirtyGroups[$old['survey_id'].'/'.$old['email_digest']]=TRUE;
          $this->audit((int)$old['id'],'removed','Confirmed absent from complete source snapshot.'); $counts['removed']++;
        }
        $groups=[];
        foreach ($this->all() as $r) if ($r['source_present']) $groups[$r['survey_id'].'/'.$r['email_digest']][]=$r;
        foreach ($groups as $key=>$rows) {
          $ids=array_column($rows,'id');$choices=array_unique(array_column($rows,'duplicate_choice'));
          $resolved=count($choices)===1 && in_array(reset($choices),$ids) && !isset($dirtyGroups[$key]);
          $conflict=count($rows)>1 && !$resolved;
          foreach ($rows as $row) {
            $fields=['duplicate_conflict'=>(int)$conflict];
            if ($conflict && (!$row['duplicate_conflict'] || isset($dirtyGroups[$key]))) {
              $fields+=['duplicate_choice'=>0,'status'=>'pending','reviewer_uid'=>0,'reviewed_at'=>0,'reason'=>'Unresolved same-survey email conflict.'];
              if (!$row['duplicate_conflict'] || isset($dirtyGroups[$key])) $this->audit((int)$row['id'],'duplicate_conflict','Duplicate group requires explicit review; email ownership is not verified.');
            } elseif (count($rows)===1) $fields['duplicate_choice']=0;
            $this->update((int)$row['id'],$fields);
          }
        }
        unset($tx); return $counts;
      } catch (\Throwable $e) { $tx->rollBack(); throw $e; }
    });
  }
  private function current(array $row, string $expected): void {
    $s=$this->source->getSubmission($row['survey_id'],$row['response_id']);
    if (!$row['source_present'] || !$s || !hash_equals($row['fingerprint'],$expected) || !hash_equals($expected,$this->validator->fingerprint($s))) throw new \RuntimeException('Source response changed or is absent. Synchronize and reload before review.');
  }
  private function reason(string $reason): string {
    $reason=trim($reason);if ($reason==='' || mb_strlen($reason)>1000) throw new \InvalidArgumentException('An internal reason of 1–1000 characters is required.');return $reason;
  }
  public function decide(int $id, string $action, string $reason, string $expected, ?string $signedVersion=NULL): void {
    $this->requirePermission('review lit contributions'); $reason=$this->reason($reason);
    $this->locked(function () use ($id,$action,$reason,$expected,$signedVersion) {
      $row=$this->get($id);
      if ($signedVersion!==NULL && !hash_equals($this->tokenForRow($row),$signedVersion)) throw new \RuntimeException('Review version changed. Reload the response.');
      $this->current($row,$expected);
      if (!in_array($action,['approved','rejected','pending'],TRUE)) throw new \InvalidArgumentException('Unknown decision.');
      if ($action!=='pending' && $row['status']!=='pending') throw new \RuntimeException('Return this response to pending before changing its decision.');
      if($action==='approved' && !empty($this->source->getSubmission($row['survey_id'],$row['response_id'])['answers']['flags']))throw new \RuntimeException('Resolve questionnaire references before approval; non-NJ responses cannot contribute to this map.');
      if ($action==='approved' && $row['duplicate_conflict']) throw new \RuntimeException('Resolve the duplicate conflict before approval.');
      $tx=$this->db->startTransaction();
      try {
        $this->update($id,['status'=>$action,'reason'=>$reason,'reviewer_uid'=>(int)$this->account->id(),'reviewed_at'=>time()]);
        $this->audit($id,$action,$reason);unset($tx);
      } catch (\Throwable $e) {$tx->rollBack();throw $e;}
    });
  }
  public function resolve(int $id, string $reason, string $expected, ?string $signedVersion=NULL): void {
    $this->requirePermission('review lit contributions');$reason=$this->reason($reason);
    $this->locked(function () use($id,$reason,$expected,$signedVersion) {
      $row=$this->get($id);
      if ($signedVersion!==NULL && !hash_equals($this->tokenForRow($row),$signedVersion)) throw new \RuntimeException('Review version changed. Reload the response.');
      $this->current($row,$expected);
      $group=array_filter($this->all(),fn($r)=>$r['source_present'] && $r['survey_id']===$row['survey_id'] && $r['email_digest']===$row['email_digest']);
      if (count($group)<2 || !$row['duplicate_conflict']) throw new \RuntimeException('No unresolved duplicate group.');
      foreach ($group as $r) $this->current($r,$r['fingerprint']);
      $tx=$this->db->startTransaction();
      try {
        foreach ($group as $r) {
          $chosen=(int)$r['id']===$id;
          $this->update((int)$r['id'],['duplicate_choice'=>$id,'duplicate_conflict'=>0,'status'=>$chosen?'pending':'rejected','reviewer_uid'=>(int)$this->account->id(),'reviewed_at'=>time(),'reason'=>$reason]);
          $this->audit((int)$r['id'],$chosen?'duplicate_selected':'duplicate_excluded',$reason);
        }
        unset($tx);
      } catch (\Throwable $e) {$tx->rollBack();throw $e;}
    });
  }
}
