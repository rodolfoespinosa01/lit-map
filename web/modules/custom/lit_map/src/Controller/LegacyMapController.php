<?php
namespace Drupal\lit_map\Controller;
use Symfony\Component\HttpFoundation\{Request,RedirectResponse};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
final class LegacyMapController {
  public function redirect(Request $request):RedirectResponse {
    $id=\Drupal::state()->get('lit_map.local_page');$node=$id?\Drupal::entityTypeManager()->getStorage('node')->load($id):NULL;
    if(!$node || !$node->access('view'))throw new NotFoundHttpException();
    $query=$request->query->all();
    if($node->hasField('field_lit_components'))foreach($node->field_lit_components->referencedEntities() as $paragraph){
      if($paragraph->bundle()!=='lit_map')continue;
      foreach($query as $key=>$value)if(str_starts_with($key,'lit_'))$query['p'.$paragraph->uuid().'_'.$key]=$value;
      break;
    }
    return new RedirectResponse($node->toUrl('canonical',['query'=>$query])->toString(),302,['Cache-Control'=>'no-store']);
  }
}
