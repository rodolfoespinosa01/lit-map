<?php
namespace Drupal\lit_contribution\Service;
use Drupal\Core\Database\Connection;
use Drupal\lit_contribution\Source\PublishedBaselineAndExports;

/** Preview and append/upsert import; never grants review approval or removes rows. */
final class ExportImporter {
  public function __construct(private readonly Connection $db,private readonly ExportStore $store,private readonly SubmissionValidator $validator,private readonly ReviewManager $reviews){}
  public function preview(array $rows):array {\Drupal::service('lit_contribution.owner')->requireOwner();return $this->plan($rows);}
  private function plan(array $rows):array {
    if(!$rows || count($rows)>1000)throw new \InvalidArgumentException('Supply 1–1,000 records.');
    $existing=[];$reserved=[];
    foreach($this->db->select('lit_contribution_review','r')->fields('r')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $r){$key=$r['survey_id'].'/'.$r['response_id'];$existing[$key]=$r;$reserved[$r['survey_id'].'/'.$r['email_digest']]??=$key;}
    $counts=array_fill_keys(['new','already_imported','duplicate_email','invalid','changed','permission_ineligible'],0);$details=[];$valid=[];$occurrences=[];
    foreach($rows as $i=>$raw){
      try{$s=$this->validator->normalize($raw,FALSE);$key=$s['survey_id'].'/'.$s['response_id'];$occurrences[$key]=($occurrences[$key]??0)+1;$valid[]=['row'=>$i+1,'key'=>$key,'submission'=>$s];}
      catch(\Throwable $e){$counts['invalid']++;$details[]=['row'=>$i+1,'result'=>'invalid','message'=>'Check required identity/email/timestamp values, county/municipality references and fixed-choice answers.'];}
    }
    usort($valid,fn($a,$b)=>[strtotime($a['submission']['submitted_at']),$a['key']]<=>[strtotime($b['submission']['submitted_at']),$b['key']]);
    $accepted=[];
    foreach($valid as $item){$s=$item['submission'];$key=$item['key'];$old=$existing[$key]??NULL;$hash=$this->validator->fingerprint($s);$digest=hash_hmac('sha256',$s['survey_id'].'\0'.$s['email'],\Drupal::service('private_key')->get());$group=$s['survey_id'].'/'.$digest;
      $type='new';$message='New response; review required.';
      if($occurrences[$key]>1){$type='invalid';$message='Repeated survey/response ID within this file; all occurrences excluded.';}
      elseif(PublishedBaselineAndExports::reserved($s['survey_id'])){
        if($old && $old['origin']==='fixture' && hash_equals($old['fingerprint'],$hash)){$type='already_imported';$message='Unchanged seeded baseline; no mutation.';}
        else{$type='invalid';$message='Reserved fixture survey ID; uploads cannot change demo records.';}
      }
      elseif(isset($reserved[$group]) && $reserved[$group]!==$key){$type='duplicate_email';$message='Email already reserved in this survey by another accepted response (or an earlier row by submission time/ID). Excluded; owner must reconcile with the source. Existing acceptance is never replaced.';}
      elseif($old && $old['origin']!=='upload'){$type='invalid';$message='Existing non-upload source reference; owner must reconcile its source.';}
      elseif($old){$type=hash_equals($old['fingerprint'],$hash)?'already_imported':'changed';$message=$type==='changed'?'Material change; prior decision returns to pending.':'Identical response; decision preserved.';}
      if(!in_array($type,['invalid','duplicate_email'],TRUE)){
        if($s['answers']['aggregate_permission']!=='yes'){$counts['permission_ineligible']++;$message.=' Publication permission absent; excluded from eligible queue and publication.';}
        if(!PublishedBaselineAndExports::reserved($s['survey_id'])){$reserved[$group]=$key;$accepted[]=$item+['kind'=>$type,'fingerprint'=>$hash,'email_digest'=>$digest,'old'=>$old];}
      }
      $counts[$type]++;$details[]=['row'=>$item['row'],'result'=>$type,'message'=>$message];
    }
    usort($details,fn($a,$b)=>$a['row']<=>$b['row']);
    $report=['counts'=>$counts,'rows'=>$details];
    return $report+['signature'=>hash('sha256',json_encode([$report,$this->store->pointer(),array_map(fn($r)=>[$r['fingerprint'],$r['status'],$r['reviewed_at']],$existing)])),'accepted'=>$accepted];
  }
  public function confirm(array $rows,string $signature):array {
    \Drupal::service('lit_contribution.owner')->requireOwner();
    return $this->apply($rows,$signature);
  }
  private function apply(array $rows,string $signature):array {
    return $this->reviews->locked(function()use($rows,$signature){
      $p=$this->plan($rows);if(!hash_equals($p['signature'],$signature))throw new \RuntimeException('Source/review state changed since preview. Preview the file again.');
      $expectedPointer=$this->store->pointer();$snapshot=$this->store->snapshot();foreach($p['accepted'] as $item)$snapshot[$item['key']]=$item['submission'];ksort($snapshot);
      // Immutable private source first; transactional pointer and metadata second.
      $hash=$this->store->write($snapshot);$tx=$this->db->startTransaction();
      try{
        foreach($p['accepted'] as $item){$s=$item['submission'];$old=$item['old'];
          // Unique database reservation is a second barrier if a lock ever expires.
          if($old && $old['email_digest']!==$item['email_digest'])$this->db->delete('lit_contribution_email_reservation')->condition('survey_id',$s['survey_id'])->condition('response_id',$s['response_id'])->execute();
          $held=$this->db->select('lit_contribution_email_reservation','e')->fields('e',['response_id'])->condition('survey_id',$s['survey_id'])->condition('email_digest',$item['email_digest'])->execute()->fetchField();
          if($held!==FALSE && $held!==$s['response_id'])throw new \RuntimeException('Concurrent email reservation conflict. Preview again.');
          if($held===FALSE)$this->db->insert('lit_contribution_email_reservation')->fields(['survey_id'=>$s['survey_id'],'response_id'=>$s['response_id'],'email_digest'=>$item['email_digest']])->execute();
          if($item['kind']==='already_imported'){
            $this->db->update('lit_contribution_review')->fields(['source_updated_at'=>$s['updated_at'],'synced_at'=>time()])->condition('id',$old['id'])->execute();continue;
          }
          $fields=['fingerprint'=>$item['fingerprint'],'email_digest'=>$item['email_digest'],'source_present'=>1,'submitted_at'=>$s['submitted_at'],'submitted_day'=>SubmissionDates::day($s['submitted_at'])??'','submitted_epoch'=>strtotime($s['submitted_at'])?:0,'source_updated_at'=>$s['updated_at'],'synced_at'=>time(),'status'=>'pending','reviewer_uid'=>0,'reviewed_at'=>0,'reason'=>'Uploaded source response awaits review.','duplicate_choice'=>0,'duplicate_conflict'=>0,'origin'=>'upload','publication_permission'=>(int)($s['answers']['aggregate_permission']==='yes' || (($s['schema_version']??1)===2 && $s['answers']['map_permission']==='yes'))];
          if($old){$id=(int)$old['id'];$this->db->update('lit_contribution_review')->fields($fields)->condition('id',$id)->execute();}
          else $id=(int)$this->db->insert('lit_contribution_review')->fields($fields+['survey_id'=>$s['survey_id'],'response_id'=>$s['response_id'],'imported_at'=>time()])->execute();
          $this->db->insert('lit_contribution_audit')->fields(['review_id'=>$id,'action'=>$old?'upload_changed':'upload_imported','actor_uid'=>(int)\Drupal::currentUser()->id(),'occurred_at'=>time(),'reason'=>$old?'Material change; renewed review required.':'Normalized export accepted; pending.'])->execute();
        }
        $this->store->point($hash,$expectedPointer);
        $this->db->insert('lit_contribution_import_history')->fields(['actor_uid'=>(int)\Drupal::currentUser()->id(),'occurred_at'=>time(),'summary'=>json_encode(['counts'=>$p['counts'],'rows'=>$p['rows']],JSON_THROW_ON_ERROR)])->execute();
        unset($tx);return $p['counts'];
      }catch(\Throwable $e){$tx->rollBack();throw $e;}
    });
  }
  /** Internal adapter boundary: local IDs/clock are generated here, never client supplied. */
  public function local(array $answers,string $email):array {
    if(!\Drupal::service('lit_contribution.invitation')->localEnabled())throw new \RuntimeException('Local demonstration unavailable.');
    $now=gmdate('Y-m-d\TH:i:s+00:00');
    $row=['schema_version'=>2,'survey_id'=>'LIT_LOCAL_QUESTIONNAIRE_V2','response_id'=>'LOCAL_'.bin2hex(random_bytes(16)),'email'=>$email,'submitted_at'=>$now,'updated_at'=>$now,'answers'=>$answers];
    $row=$this->validator->normalize($row,FALSE);$p=$this->plan([$row]);
    if($p['counts']['new']!==1)throw new \RuntimeException('Unable to accept this demonstration response.');
    return $this->apply([$row],$p['signature']);
  }
  public function resolveQuestionnaire(int $id,array $resolutions):void {
    $this->reviews->requirePermission('review lit contributions');$r=$this->reviews->get($id);
    if($r['origin']!=='upload')throw new \RuntimeException('Resolve uploaded questionnaire references only.');
    $s=$this->store->snapshot()[$r['survey_id'].'/'.$r['response_id']]??NULL;
    if(!$s || $s['schema_version']!==2)throw new \RuntimeException('Not a questionnaire response.');
    $s['answers']['resolutions']=$resolutions;$s['updated_at']=gmdate('Y-m-d\TH:i:s+00:00');
    $p=$this->plan([$s]);$this->apply([$s],$p['signature']);
  }
  public function history():array{\Drupal::service('lit_contribution.owner')->requireOwner();return $this->db->select('lit_contribution_import_history','h')->fields('h')->orderBy('id','DESC')->range(0,20)->execute()->fetchAll(\PDO::FETCH_ASSOC);}
}
