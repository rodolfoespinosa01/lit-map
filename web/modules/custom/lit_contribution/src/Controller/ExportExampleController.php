<?php
namespace Drupal\lit_contribution\Controller;
use Symfony\Component\HttpFoundation\Response;
final class ExportExampleController {
  public function download(string $format):Response {
    \Drupal::service('lit_contribution.owner')->requireOwner();if(!in_array($format,['csv','json'],TRUE))throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    $bytes=file_get_contents(dirname(DRUPAL_ROOT).'/fixtures/lit-export/questionnaire-v2.'.$format);
    return new Response($bytes,200,['Content-Type'=>$format==='csv'?'text/csv; charset=UTF-8':'application/json','Content-Disposition'=>'attachment; filename="lit-simulated-export.'.$format.'"','X-Content-Type-Options'=>'nosniff']);
  }
}
