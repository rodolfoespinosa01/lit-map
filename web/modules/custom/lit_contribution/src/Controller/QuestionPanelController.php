<?php
namespace Drupal\lit_contribution\Controller;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Drupal\lit_contribution\Service\{QuestionPanels,SubmissionValidator};
final class QuestionPanelController {
  public function build(Request $request):JsonResponse {
    try{
      $hash=\Drupal::database()->select('lit_contribution_publication','p')->fields('p',['file_hash'])->condition('id',1)->execute()->fetchField();$d=$hash?\Drupal::service('lit_contribution.writer')->read($hash):[];
      if($request->query->get('publication') && $request->query->get('publication')!==$hash)return new JsonResponse(['error'=>'Publication changed. Refresh the map to keep counts and panels aligned.'],409,['Cache-Control'=>'no-store']);
      $cohorts=isset($d['questionnaire_hash'])?\Drupal::service('lit_contribution.writer')->read($d['questionnaire_hash'])['cohorts']:($d['questionnaire']??[]);
      $q=$request->query->all();
      if(isset($q['counties'])){if(!is_array($q['counties']) || count($q['counties'])>21)throw new \InvalidArgumentException();foreach($q['counties'] as $c)if(!is_string($c) || !preg_match('/^34[0-9]{3}$/D',$c))throw new \InvalidArgumentException();$cohorts=array_values(array_filter($cohorts,fn($c)=>in_array($c['county_geoid'],$q['counties'],TRUE)));}
      $languages=$q['languages']??[];if(!is_array($languages) || count($languages)>count(SubmissionValidator::languages()))throw new \InvalidArgumentException();foreach($languages as $language)if(!is_string($language))throw new \InvalidArgumentException();$languages=array_values(array_intersect(array_keys(SubmissionValidator::languages()),$languages));
      foreach(['county','municipality','from','through'] as $key)if(isset($q[$key]) && (!is_string($q[$key]) || strlen($q[$key])>100))throw new \InvalidArgumentException();
      $out=QuestionPanels::filter($cohorts,$languages,$q['county']??'',$q['municipality']??'',$q['from']??'',$q['through']??'',(int)(\Drupal::config('lit_contribution.settings')->get('panel_threshold')??5));
      return new JsonResponse($out,200,['Cache-Control'=>'no-store']);
    }catch(\Throwable $e){return new JsonResponse(['error'=>'Questionnaire aggregates unavailable or invalid date range.'],400,['Cache-Control'=>'no-store']);}
  }
}
