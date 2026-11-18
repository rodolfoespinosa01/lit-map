<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\{FormBase,FormStateInterface};
use Drupal\lit_contribution\Service\SubmissionValidator;
final class QuestionnaireResolutionForm extends FormBase {
  public function getFormId():string{return 'lit_questionnaire_resolution';}
  public function buildForm(array $form,FormStateInterface $state,?int $review=NULL):array {
    $reviews=\Drupal::service('lit_contribution.reviews');$s=$reviews->submission($review);if(!$s || $s['schema_version']!==2)throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    $form['review']=['#type'=>'value','#value'=>$review];$form['version']=['#type'=>'hidden','#default_value'=>$reviews->reviewToken($review)];$form['#cache']['max-age']=0;
    $v=\Drupal::service('lit_contribution.validator');$form['notice']=['#plain_text'=>'Resolve references only when the raw response supports the choice. Do not merge distinct languages or infer native identity from age. Non-NJ residence remains ineligible. Saving a material correction returns the response to pending; approval is separate.'];
    $form['county']=['#type'=>'select','#title'=>'Resolved NJ county','#required'=>TRUE,'#options'=>[''=>'Choose a supported county']+array_column($v->counties()['counties'],'name','geoid'),'#default_value'=>$s['answers']['county_geoid']];
    $form['municipality']=['#type'=>'select','#title'=>'Resolved municipality','#options'=>['__unresolved'=>'Leave unresolved',''=>'Explicitly mark municipality unspecified']+array_column($v->references()['municipalities'],'name','id'),'#default_value'=>$s['answers']['municipality']??(in_array('Unmatched or ambiguous municipality',$s['answers']['flags'])?'__unresolved':'')];
    foreach($s['answers']['questions']['q07'] as $i=>$slot)if(trim($slot['text'])!=='')$form['language_'.$i]=['#type'=>'select','#title'=>'Resolve slot '.($i+1).' ('.$slot['text'].')','#options'=>[''=>'Leave automatic/unresolved']+\Drupal::service('lit_map.references')->resolvableLanguages(),'#default_value'=>$s['answers']['resolutions']['languages'][$i]??''];
    $form['submit']=['#type'=>'submit','#value'=>'Save reference resolution; require review'];return $form;
  }
  public function submitForm(array &$form,FormStateInterface $state):void {
    try{$id=(int)$state->getValue('review');\Drupal::service('lit_contribution.reviews')->verifyReviewToken($id,(string)$state->getValue('version'));$r=['county'=>$state->getValue('county'),'languages'=>[]];if($state->getValue('municipality')!=='__unresolved')$r['municipality']=$state->getValue('municipality')?:NULL;for($i=0;$i<3;$i++)if($l=$state->getValue('language_'.$i))$r['languages'][$i]=$l;\Drupal::service('lit_contribution.importer')->resolveQuestionnaire($id,$r);$this->messenger()->addStatus('Resolution saved. Review is still required.');$state->setRedirect('lit_contribution.detail',['review'=>$id]);}catch(\Throwable $e){$this->messenger()->addError('Resolution not saved. Check county/municipality compatibility and reload the response.');}
  }
}
