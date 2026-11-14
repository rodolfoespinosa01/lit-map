<?php
namespace Drupal\lit_contribution\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

final class OwnerDashboardController extends ControllerBase {
  public function build(): array {
    \Drupal::service('lit_contribution.owner')->requireOwner();
    $build=['#cache'=>['max-age'=>0,'contexts'=>['user']],'#attributes'=>['class'=>['lit-owner-dashboard']],'#type'=>'container','#attached'=>['library'=>['lit_contribution/admin']]];
    $build['notice']=['#type'=>'html_tag','#tag'=>'p','#value'=>'Superadmin workspace. Assign administrator emails and passwords here. Review decisions and publication are separate; only the superadmin can publish. No email delivery is needed for LiT login.'];
    $links=[
      'administrators'=>'Manage administrator emails',
      'pending'=>'Review pending contributions',
      'list'=>'All contribution reviews',
      'publish'=>'Publish approved aggregates',
      'settings'=>'Contribution settings',
      'resources'=>'Manage recordings and resources',
      'reconciliation'=>'Reconcile source and publication',
      'import'=>'Import survey responses',
      'datasets'=>'Dataset composition and preserved fixtures',
      'map'=>'View public map',
    ];
    foreach($links as $route=>$label)$build[$route]=['#type'=>'link','#title'=>$label,'#url'=>Url::fromRoute('lit_contribution.'.$route),'#prefix'=>'<p>','#suffix'=>'</p>'];
    return $build;
  }
}
