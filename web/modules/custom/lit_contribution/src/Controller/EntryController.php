<?php
namespace Drupal\lit_contribution\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
final class EntryController extends ControllerBase {
  public function build(): array {
    $build=['#type'=>'container','#attributes'=>['class'=>['lit-owner-dashboard']],'#cache'=>['max-age'=>0],'#attached'=>['library'=>['lit_contribution/admin']]];
    $build['notice']=['#type'=>'html_tag','#tag'=>'p','#value'=>'The public language map needs no login. Administrative access requires an assigned email and password.'];
    foreach(['map'=>'View map','superadmin_login'=>'Superadmin login','administrator_login'=>'Administrator login'] as $route=>$label)$build[$route]=['#type'=>'link','#title'=>$label,'#url'=>Url::fromRoute('lit_contribution.'.$route),'#prefix'=>'<p>','#suffix'=>'</p>'];
    return $build;
  }
}
