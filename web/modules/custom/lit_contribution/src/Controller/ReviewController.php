<?php
namespace Drupal\lit_contribution\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\lit_contribution\Form\DecisionForm;
use Drupal\lit_contribution\Service\ReviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ReviewController extends ControllerBase {
  public function __construct(private readonly ReviewManager $reviews) {}
  public static function create(ContainerInterface $c): static { return new static($c->get('lit_contribution.reviews')); }
  private function stamp(int|string $time): string { return $time?gmdate('Y-m-d H:i:s \U\T\C',(int)$time):'Not yet'; }
  private function base(): array {
    return ['#cache'=>['max-age'=>0], 'notice'=>['#type'=>'html_tag','#tag'=>'p','#value'=>$this->t('Private imported contributions. The map includes the selected synthetic baseline, if any. No live Qualtrics connection. New/changed responses require review. Decisions affect the public map only after a successful publication. Email matches do not verify ownership.')]];
  }
  public function pending(): array { return $this->queue(TRUE); }
  public function listing(): array { return $this->queue(FALSE); }
  private function queue(bool $pendingOnly): array {
    $rows=$this->reviews->listing($pendingOnly);$build=$this->base();
    $build['date_filters']=$this->formBuilder()->getForm(\Drupal\lit_contribution\Form\ReviewDateFilterForm::class);
    $build['queue_link']=['#type'=>'link','#title'=>$pendingOnly?'All contribution reviews':'Pending contribution reviews','#url'=>Url::fromRoute($pendingOnly?'lit_contribution.list':'lit_contribution.pending')];
    foreach (['administrators'=>['Manage administrators','manage lit administrators'], 'reconciliation'=>['Publication reconciliation','administer lit contribution settings'], 'import'=>['Import survey responses','administer lit contribution settings'], 'publish'=>['Publish approved aggregates','publish lit contributions'], 'settings'=>['Invitation settings','administer lit contribution settings']] as $route=>[$label,$permission]) {
      if ($this->currentUser()->hasPermission($permission)) $build[$route]=['#type'=>'link','#title'=>$label,'#url'=>Url::fromRoute('lit_contribution.'.$route),'#prefix'=>'<p>','#suffix'=>'</p>'];
    }
    $build['table']=['#type'=>'table','#header'=>['Source reference','Status','Duplicate conflict','Submitted (source offset)','First imported','Last reviewed','Last included in publication','Currently included'],'#rows'=>[],'#empty'=>'No eligible imported contributor requests. Seeded fixture requests are separate.'];
    foreach ($rows as $r) $build['table']['#rows'][]=[
      ['data'=>['#type'=>'link','#title'=>$r['survey_id'].' / '.$r['response_id'],'#url'=>Url::fromRoute('lit_contribution.detail',['review'=>$r['id']])]],
      $r['source_present']?$r['status']:'Absent from source', $r['duplicate_conflict']?'Unresolved — excluded':'None / resolved',
      $r['submitted_at'],$this->stamp($r['imported_at']),$this->stamp($r['reviewed_at']),$this->stamp($r['published_at']),$r['in_publication']?'Yes (last snapshot)':'No'];
    $build['pager']=['#type'=>'pager'];
    $build['scope']=['#plain_text'=>'Contributor queue: uploaded and local-demo responses only. Publication: selected baseline (if any) plus approved contributions.'];
    return $build;
  }
  public function detail(int $review): array {
    $r=$this->reviews->get($review);$build=$this->base();
    $build['back']=['#type'=>'link','#title'=>'Back to contribution list','#url'=>Url::fromRoute('lit_contribution.list')];
    $metadata=['Survey'=>$r['survey_id'],'Response'=>$r['response_id'],'Status'=>$r['status'], 'Source present'=>$r['source_present']?'Yes':'No',
      'Submitted'=>$r['submitted_at'],'Source updated'=>$r['source_updated_at'],'First imported'=>$this->stamp($r['imported_at']),
      'Last synchronized'=>$this->stamp($r['synced_at']),'Last reviewed'=>$this->stamp($r['reviewed_at']),
      'Reviewer'=>$r['reviewer_uid']?('UID '.$r['reviewer_uid'].' · '.($this->entityTypeManager()->getStorage('user')->load($r['reviewer_uid'])?->label()??'Deleted account')):'None',
      'Last published (included)'=>$this->stamp($r['published_at']),'Included in last publication'=>$r['in_publication']?'Yes':'No',
      'Duplicate conflict'=>$r['duplicate_conflict']?'Unresolved; cannot publish':'None / resolved','Duplicate chosen review ID'=>$r['duplicate_choice']?:'None', 'Internal reason'=>$r['reason']];
    $build['metadata']=['#type'=>'table','#header'=>['Review metadata','Value'],'#rows'=>[]];
    foreach ($metadata as $key=>$value) $build['metadata']['#rows'][]=[$key,['data'=>['#plain_text'=>(string)$value]]];
    try {
      $s=$this->reviews->submission($review);
      if(($s['schema_version']??1)===2 && $r['origin']==='upload')$build['resolve']=['#type'=>'link','#title'=>'Resolve questionnaire language/place references','#url'=>Url::fromRoute('lit_contribution.resolve_questionnaire',['review'=>$review])];
      $build['source']=['#type'=>'details','#title'=>'Private source response (fetched now; not stored in Drupal)','#open'=>TRUE,
        'data'=>['#prefix'=>'<pre>','#suffix'=>'</pre>','#plain_text'=>$s?json_encode($s,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):'Confirmed absent from mock source.']];
    } catch (\Throwable $e) { $this->getLogger('lit_contribution')->error('Private source read failed: @type',['@type'=>get_class($e)]);$build['source']=['#plain_text'=>'Mock source unavailable. Retry after checking the local fixture.']; }
    // Source answers are outside the form: Form API stores only review metadata.
    $build['decision']=$this->formBuilder()->getForm(DecisionForm::class,$review);
    $build['audit']=['#type'=>'table','#caption'=>'Internal audit trail','#header'=>['Action','Actor UID','Occurred (UTC)','Reason'],'#rows'=>[]];
    foreach ($this->reviews->auditTrail($review) as $a) $build['audit']['#rows'][]=[$a['action'],$a['actor_uid'],$this->stamp($a['occurred_at']),['data'=>['#plain_text'=>$a['reason']]]];
    return $build;
  }
}
