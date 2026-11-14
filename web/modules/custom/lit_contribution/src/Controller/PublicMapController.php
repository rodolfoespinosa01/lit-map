<?php
namespace Drupal\lit_contribution\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Html;
use Drupal\lit_contribution\Service\Publication;
use Drupal\lit_contribution\Service\Invitation;
use Drupal\lit_map\Service\BubblePreviewData;
use Symfony\Component\DependencyInjection\ContainerInterface;
final class PublicMapController extends ControllerBase {
  public function __construct(private readonly Publication $publication, private readonly BubblePreviewData $geometry, private readonly Invitation $invitation) {}
  public static function create(ContainerInterface $c): static { return new static($c->get('lit_contribution.publication'),$c->get('lit_map.bubble_data'),$c->get('lit_contribution.invitation')); }
  public function build(): array {
    $data=NULL;$date=NULL;
    try {
      $public=$this->publication->publicData();
      if ($public && !in_array($public['survey']['schemaVersion'],[3,4],TRUE))throw new \RuntimeException('Publish a combination aggregate before displaying combined counts.');
      if ($public) {$data=$this->geometry->load($public['survey']);$date=$public['date'];}
    } catch (\Throwable $e) { $this->getLogger('lit_contribution')->error('Public aggregate read failed: @type',['@type'=>get_class($e)]); }
    $id=Html::getUniqueId('lit-map-approved');
    $build=['#theme'=>'lit_map_bubble_preview','#instance_id'=>$id,'#available'=>$data!==NULL,'#approved_preview'=>TRUE,'#publication_date'=>$date,'#invitation_url'=>$this->invitation->url(),
      '#attached'=>['library'=>['lit_map/bubbles']],'#cache'=>['max-age'=>0]];
    if ($data) $build['#attached']['drupalSettings']['litMapBubbles'][$id]=$data+['openingPreset'=>TRUE,'publicationHash'=>$public['hash'],'panelsUrl'=>\Drupal\Core\Url::fromRoute('lit_contribution.panels')->toString(),'today'=>(new \DateTimeImmutable('now',new \DateTimeZone('America/New_York')))->format('Y-m-d')];
    if ($data) {
      try {
        $cards=\Drupal::service('lit_contribution.resources')->cards();
        $build['#resources']=$cards;
        $build['#attached']['drupalSettings']['litMapBubbles'][$id]['resources']=array_map(fn($r)=>array_diff_key($r,['card'=>TRUE]),$cards);
      } catch (\Throwable $e) { $this->getLogger('lit_contribution')->error('Public resources unavailable: @type',['@type'=>get_class($e)]); }
    }
    return $build;
  }
}
