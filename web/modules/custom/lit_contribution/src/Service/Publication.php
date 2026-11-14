<?php
namespace Drupal\lit_contribution\Service;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\lit_contribution\Source\SubmissionSourceInterface;
use Drupal\lit_map\Service\SurveyNormalizer;

final class Publication {
  public function __construct(private readonly Connection $db, private readonly SubmissionSourceInterface $source,
    private readonly SubmissionValidator $validator, private readonly ReviewManager $reviews,
    private readonly AtomicAggregateWriter $writer, private readonly SurveyNormalizer $normalizer,
    private readonly AccountProxyInterface $account) {}
  public function publish(): array {
    $this->reviews->requirePermission('publish lit contributions');
    return $this->reviews->locked(function () {
      $snapshot=$this->source->listSubmissions();
      $rows=$this->reviews->rows();
      $keys=[];
      foreach ($rows as $id=>$row) {
        $key=$row['survey_id'].'/'.$row['response_id'];$keys[$key]=TRUE;$s=$snapshot[$key]??NULL;
        if ((bool)$row['source_present'] !== ($s!==NULL) || ($s && !hash_equals($row['fingerprint'],$this->validator->fingerprint($s)))) throw new \RuntimeException('Source changed since synchronization. Synchronize and review before publishing.');
      }
      if (array_diff_key($snapshot,$keys)) throw new \RuntimeException('New source responses require synchronization before publication.');
      $result=AggregateBuilder::build($snapshot,$rows);
      $included=$result['included'];$aggregate=$result['aggregate'];
      $this->normalizer->normalize($aggregate,$this->validator->counties(),$this->validator->references());
      $panelHash=$this->writer->write(['cohorts'=>$result['questionnaire']]);
      $document=['publication_version'=>3,'updated_date'=>gmdate('Y-m-d'),'aggregate'=>$aggregate,'questionnaire_hash'=>$panelHash];
      // Write immutable file first. No pointer/metadata changes if writing fails.
      $hash=$this->writer->write($document);
      $tx=$this->db->startTransaction();
      try {
        $now=time();
        $reset=$this->db->update('lit_contribution_review')->fields(['in_publication'=>0]);
        if($this->reviews->scope())$reset->condition('survey_id',$this->reviews->scope());
        if($rows)$reset->condition('id',array_keys($rows),'IN')->execute();
        if ($included) $this->db->update('lit_contribution_review')->fields(['in_publication'=>1,'published_at'=>$now])->condition('id',$included,'IN')->execute();
        $this->db->merge('lit_contribution_publication')->key(['id'=>1])->fields(['file_hash'=>$hash,'published_at'=>$now,'publisher_uid'=>(int)$this->account->id()])->execute();
        if($this->reviews->scope())$this->db->merge('lit_contribution_dataset')->key(['survey_id'=>$this->reviews->scope()])->fields(['file_hash'=>$hash,'published_at'=>$now,'publisher_uid'=>(int)$this->account->id()])->execute();
        unset($tx);
      } catch (\Throwable $e) { $tx->rollBack();throw $e; }
      return ['included'=>count($included),'records'=>count($aggregate['records']),'updated_date'=>$document['updated_date']];
    });
  }
  /** Private, read-only reconciliation. Never attached to public map settings. */
  public function reconcile(): array {
    $this->reviews->requirePermission('administer lit contribution settings');
    return $this->reviews->locked(function () {
      $snapshot=$this->source->listSubmissions();
      $rows=$this->reviews->rows();
      $publication=$this->db->select('lit_contribution_publication','p')->fields('p')->condition('id',1)->execute()->fetchAssoc() ?: NULL;
      $document=$publication?$this->writer->read($publication['file_hash']):NULL;
      $expected=AggregateBuilder::build($snapshot,$rows);
      $counts=['source'=>count($snapshot),'pending'=>0,'rejected'=>0,'duplicate'=>0,'permission'=>0,'absent'=>0,'unsynchronized'=>0,'approved_eligible'=>count($expected['included']),'published_eligible'=>0];
      $details=[];$keys=[];$published=[];
      foreach($rows as $id=>$r) {
        $key=$r['survey_id'].'/'.$r['response_id'];$keys[$key]=TRUE;$s=$snapshot[$key]??NULL;
        $flags=AggregateBuilder::exclusions($s,$r);
        foreach(['pending','rejected','duplicate','permission','absent'] as $category)$counts[$category]+=(int)$flags[$category];
        $changed=((bool)$r['source_present']!==($s!==NULL)) || ($s && !hash_equals($r['fingerprint'],$this->validator->fingerprint($s)));
        $counts['unsynchronized']+=(int)$changed;
        if($r['in_publication'])$published[]=(int)$id;
        $details[]=[$key,$r['status'],implode(', ',array_keys(array_filter($flags))) ?: 'Eligible',$changed?'Yes':'No',$r['in_publication']?'Yes':'No'];
      }
      foreach(array_diff_key($snapshot,$keys) as $key=>$s){$counts['unsynchronized']++;$details[]=[$key,'Not imported','Requires synchronization','Yes','No'];}
      sort($published);$counts['published_eligible']=count($published);
      $audit=$this->db->select('lit_contribution_audit','a');
      if($this->reviews->scope()){$audit->join('lit_contribution_review','r','r.id = a.review_id');$audit->condition('r.survey_id',$this->reviews->scope());}
      if($this->source instanceof \Drupal\lit_contribution\Source\PublishedBaselineAndExports)$audit->condition('a.review_id',array_keys($rows)?:[0],'IN');
      $after=$publication?(int)(clone $audit)->condition('a.occurred_at',$publication['published_at'],'>')->countQuery()->execute()->fetchField():0;
      $sameSecond=$publication?(int)(clone $audit)->condition('a.occurred_at',$publication['published_at'])->countQuery()->execute()->fetchField():0;
      $countyRecords=[];$municipalityRecords=[];
      foreach($rows as $row)if(in_array((int)$row['id'],$expected['included'],TRUE)){
        $a=$snapshot[$row['survey_id'].'/'.$row['response_id']]['answers'];$c=$a['county_geoid'];$m=$a['municipality']??'_unspecified';
        $countyRecords[$c]=($countyRecords[$c]??0)+1;$municipalityRecords[$m]=($municipalityRecords[$m]??0)+1;
      }
      $allLanguageUnion=0;$publishedSubmissions=0;
      foreach($document['aggregate']['groups']??[] as $g){$publishedSubmissions+=$g['response_count'];if($g['languages'])$allLanguageUnion+=$g['response_count'];}
      $totals=[];
      foreach($document['aggregate']['records']??[] as $r){$key=$r['county_geoid'].'/'.$r['language'];$totals[$key]=($totals[$key]??0)+$r['response_count'];}
      ksort($totals);
      return compact('publication','counts','details','totals','after','sameSecond','countyRecords','municipalityRecords')+['publicationVersion'=>$document['publication_version']??NULL,'aggregateVersion'=>$document['aggregate']['schema_version']??NULL,'allLanguageUnion'=>$allLanguageUnion,'publishedSubmissions'=>$publishedSubmissions]+['dataset'=>$this->source instanceof \Drupal\lit_contribution\Source\PublishedBaselineAndExports?'Selected 10,000-response synthetic baseline + operational uploads':$this->reviews->scope()]+[
        'matches'=>$document && $document['aggregate']===$expected['aggregate'] && $published===$expected['included'] && !$counts['unsynchronized'],
      ];
    });
  }
  /** Public-safe, allowlisted aggregate only. Never calls the private adapter. */
  public function publicData(): ?array {
    $hash=$this->db->select('lit_contribution_publication','p')->fields('p',['file_hash'])->condition('id',1)->execute()->fetchField();
    if (!$hash) return NULL;
    $document=$this->writer->read($hash);
    $version=$document['publication_version']??NULL;
    if(!in_array($version,[1,2,3],TRUE) || !is_string($document['updated_date']??NULL) || !preg_match('/^\d{4}-\d\d-\d\d$/D',$document['updated_date']) || count($document)!==($version===3?4:3))throw new \UnexpectedValueException('Invalid publication.');
    if(($document['aggregate']['schema_version']??NULL)!==$version+1)throw new \UnexpectedValueException('Publication schema mismatch.');
    return ['hash'=>$hash,'date'=>$document['updated_date'],'survey'=>$this->normalizer->normalize($document['aggregate'],$this->validator->counties(),$this->validator->references())];
  }
}
