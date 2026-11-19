<?php
namespace Drupal\lit_map\Controller;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Drupal\lit_map\Service\{QuestionPanels,SubmissionValidator};
final class QuestionPanelController {
  public function build(Request $request):JsonResponse {
    try{
      $pointer=\Drupal::state()->get('lit_map.dataset');$hash=$pointer['hash']??NULL;
      if($request->query->get('publication') && $request->query->get('publication')!==$hash)return new JsonResponse(['error'=>'Dataset changed. Refresh the map.'],409,['Cache-Control'=>'no-store']);
      $d=\Drupal::service('lit_map.dataset')->document();$cohorts=$d['cohorts']??[];
      $q=$request->query->all();
      if(isset($q['counties'])){if(!is_array($q['counties']) || count($q['counties'])>21)throw new \InvalidArgumentException();foreach($q['counties'] as $c)if(!is_string($c) || !preg_match('/^34[0-9]{3}$/D',$c))throw new \InvalidArgumentException();$cohorts=array_values(array_filter($cohorts,fn($c)=>in_array($c['county_geoid'],$q['counties'],TRUE)));}
      $languages=$q['languages']??[];if(!is_array($languages) || count($languages)>count(SubmissionValidator::languages()))throw new \InvalidArgumentException();foreach($languages as $language)if(!is_string($language))throw new \InvalidArgumentException();$languages=array_values(array_intersect(array_keys(SubmissionValidator::languages()),$languages));
      foreach(['county','municipality','from','through'] as $key)if(isset($q[$key]) && (!is_string($q[$key]) || strlen($q[$key])>100))throw new \InvalidArgumentException();
      $out=QuestionPanels::filter($cohorts,$languages,$q['county']??'',$q['municipality']??'',$q['from']??'',$q['through']??'',5);
      return new JsonResponse($out,200,['Cache-Control'=>'no-store']);
    }catch(\Throwable $e){return new JsonResponse(['error'=>'Questionnaire aggregates unavailable or invalid date range.'],400,['Cache-Control'=>'no-store']);}
  }
}
