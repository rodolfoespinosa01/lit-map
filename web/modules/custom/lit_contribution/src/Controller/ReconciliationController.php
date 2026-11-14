<?php
namespace Drupal\lit_contribution\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\lit_contribution\Service\Publication;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ReconciliationController extends ControllerBase {
  public function __construct(private readonly Publication $publication) {}
  public static function create(ContainerInterface $c): static { return new static($c->get('lit_contribution.publication')); }
  public function build(): array {
    $build=['#cache'=>['max-age'=>0]];
    try { $r=$this->publication->reconcile(); }
    catch(\Throwable $e){$this->getLogger('lit_contribution')->error('Reconciliation unavailable: @type',['@type'=>get_class($e)]);return $build+['error'=>['#plain_text'=>'Reconciliation unavailable. Check the private source and publication, then retry. No data was changed.']];}
    $p=$r['publication'];
    $build['scope']=['#plain_text'=>'Active dataset: '.($r['dataset']??'Unscoped test source')];
    $build['county_records']=['#type'=>'table','#caption'=>'Current eligible submissions by county (not sums of languages)','#header'=>['County','Eligible submissions'],'#rows'=>[]];
    foreach($r['countyRecords'] as $county=>$count)$build['county_records']['#rows'][]=[$county,$count];
    $build['municipality_records']=['#type'=>'details','#title'=>'Eligible submissions by municipality','table'=>['#type'=>'table','#header'=>['Municipality','Eligible submissions'],'#rows'=>[]]];
    foreach($r['municipalityRecords'] as $town=>$count)$build['municipality_records']['table']['#rows'][]=[$town,$count];
    $build['notice']=['#type'=>'html_tag','#tag'=>'p','#value'=>'Private, read-only reconciliation of the synthetic baseline plus operational uploads. Exclusion categories overlap: a pending or rejected record may also lack permission or be duplicate-excluded. Do not add category counts. Unresolved duplicate groups and nonchosen duplicates are excluded. Email matching does not verify ownership. Unsynchronized source changes block publication.'];
    $build['publication']=['#type'=>'table','#header'=>['Publication','Value'],'#rows'=>[
      ['Last publication (UTC)',$p?gmdate('Y-m-d H:i:s \U\T\C',(int)$p['published_at']):'None'],
      ['Immutable file SHA-256',$p['file_hash']??'None'],
      ['Publication / aggregate version',($r['publicationVersion']??'None').' / '.($r['aggregateVersion']??'None')],
      ['Published grouped submissions (schema 3)',$r['publishedSubmissions']],
      ['ANY of all published languages (each response once)',$r['allLanguageUnion']],
      ['Current eligibility and aggregates match publication',$r['matches']?'Yes':'No — synchronize/review as necessary, then publish'],
      ['Audit changes after publication',$r['after']],
      ['Audit changes in publication second',$r['sameSecond'].' (second-resolution timestamps cannot establish order within that second; aggregate and membership comparison above is independent)'],
    ]];
    $labels=['source'=>'Source records','pending'=>'Pending','rejected'=>'Rejected','duplicate'=>'Duplicate-excluded','permission'=>'Permission-excluded','absent'=>'Absent from source','unsynchronized'=>'Unsynchronized references','approved_eligible'=>'Currently approved eligible records','published_eligible'=>'Eligible records in last publication'];
    $build['counts']=['#type'=>'table','#header'=>['Category (overlapping)','Records'],'#rows'=>[]];
    foreach($labels as $key=>$label)$build['counts']['#rows'][]=[$label,$r['counts'][$key]];
    $build['totals']=['#type'=>'table','#caption'=>'Published survey responses by county and language (municipalities plus unspecified). Languages overlap; never sum these as unique people.','#header'=>['County GEOID','Language','Survey responses'],'#rows'=>[]];
    foreach($r['totals'] as $key=>$count){[$county,$language]=explode('/',$key);$build['totals']['#rows'][]=[$county,$language,$count];}
    $build['records']=['#type'=>'table','#caption'=>'First 100 private source references. The paged contributor queue contains uploads only; dataset tools summarize preserved fixtures. No raw answers or emails.','#header'=>['Source reference','Review status','Exclusions','Source changed since sync','Included in last publication'],'#rows'=>array_slice($r['details'],0,100)];
    return $build;
  }
}
