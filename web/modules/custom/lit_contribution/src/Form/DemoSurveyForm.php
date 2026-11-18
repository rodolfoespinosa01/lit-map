<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\{FormBase,FormStateInterface};
use Drupal\lit_contribution\Service\Questionnaire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
final class DemoSurveyForm extends FormBase {
  public function getFormId():string{return 'lit_questionnaire_demo';}
  public function buildForm(array $form,FormStateInterface $form_state):array {
    if(!\Drupal::service('lit_contribution.invitation')->localEnabled())throw new AccessDeniedHttpException();
    \Drupal::service('page_cache_kill_switch')->trigger();$form['#cache']['max-age']=0;$form['#attributes']['class'][]='lit-contribution-form';$form['#attributes']['class'][]='lit-questionnaire';$form['#attached']['library'][]='lit_contribution/admin';$form['#attached']['library'][]='lit_contribution/questionnaire';$form_state->disableCache();
    $session=$this->getRequest()->getSession();$session->start();
    // Drupal discards metadata-only anonymous sessions; retain a non-answer marker.
    if($this->getRequest()->isMethod('GET'))$session->set('lit_demo_opened',time());
    $form['notice']=['#markup'=>'<h2>Language Survey — LOCAL demonstration</h2><p>Placeholder study information pending the team’s approved text. Use fictional information. Participation is voluntary. This demonstration does not enter a real raffle. No contributor account or password is needed.</p><p>Placeholder research contact: Demonstration Researcher, research@example.org. Placeholder study contact: study@example.org. Contact and mailing details are intentionally omitted.</p>'];
    $form['consent']=['#type'=>'checkbox','#title'=>'I Agree: I am 18 or older, have read the information and voluntarily agree to participate in this LOCAL demonstration.','#required'=>TRUE,'#description'=>'Placeholder study agreement pending the team’s approved text. If you decline, close this page. No real raffle entry.'];
    $form['application']=['#type'=>'fieldset','#title'=>'Application fields — not questions in the original survey','#tree'=>TRUE];
    $form['application']['email']=['#type'=>'email','#title'=>'Duplicate-check email (required application field)','#required'=>TRUE,'#maxlength'=>254,'#description'=>'Used privately to prevent repeat submissions in this survey. It is not copied into optional interview or raffle contacts and does not verify email ownership. Use an example-domain email for this demonstration.'];
    $form['application']['map_permission']=['#type'=>'select','#title'=>'Allow this demonstration response to contribute to approved aggregate map displays?','#options'=>['unknown'=>'Not specified','yes'=>'Yes','no'=>'No'],'#description'=>'Separate application permission; not a question transcribed from the printout. No contacts, reflections or quotes are published.'];
    $form['demo_token']=['#type'=>'hidden','#default_value'=>\Drupal::csrfToken()->get('lit.local.demo')];
    $form['website']=['#type'=>'textfield','#title'=>'Leave this field empty','#attributes'=>['tabindex'=>'-1','autocomplete'=>'off'],'#wrapper_attributes'=>['style'=>'display:none'],'#maxlength'=>100];
    $form['questions']=['#type'=>'container','#tree'=>TRUE];
    foreach(Questionnaire::catalog() as $f){$k=$f['key'];$title=$f['number'].'. '.$f['label'];$x=['#title'=>$title,'#description'=>$f['help']??'Optional unless explicitly marked. Unanswered is not No or zero.'];
      $choice=fn($label,$options)=>['#type'=>'select','#title'=>$label,'#options'=>[''=>'— Unanswered —']+$options];
      switch($f['type']){
        case 'choice':$x+=$choice($title,$f['options']);break;
        case 'multiple':$x+=['#type'=>'checkboxes','#options'=>$f['options']];break;
        case 'age':case 'years':$x+=['#type'=>'number','#min'=>0,'#max'=>120,'#step'=>1];break;
        case 'email':$x+=['#type'=>'email','#maxlength'=>254];break;
        case 'reflection':$x+=['#type'=>'textarea','#maxlength'=>2000];break;
        case 'gender':$x+=['#type'=>'fieldset','choice'=>$choice('Gender',$f['options']),'text'=>['#type'=>'textfield','#title'=>'Different term','#maxlength'=>200,'#states'=>['visible'=>[':input[name="questions[q05][choice]"]'=>['value'=>'different']]]]];break;
        case 'contact':$x+=['#type'=>'fieldset','name'=>['#type'=>'textfield','#title'=>'Optional demonstration full name','#maxlength'=>200],'email'=>['#type'=>'email','#title'=>'Optional demonstration raffle contact email','#maxlength'=>254]];break;
        case 'languages':$x['#type']='fieldset';for($i=0;$i<3;$i++)$x[$i]=['#type'=>'fieldset','#title'=>'Language '.($i+1),'text'=>['#type'=>'textfield','#title'=>'Language '.($i+1).' spoken or understood','#maxlength'=>100],'age'=>['#type'=>'number','#title'=>'Age learning began','#min'=>0,'#max'=>120,'#step'=>1]];break;
        case 'frequency':$x['#type']='fieldset';for($i=0;$i<3;$i++)$x[$i]=$choice('Language '.($i+1),$f['options']);break;
        case 'proficiency':$x['#type']='fieldset';foreach(Questionnaire::SKILLS as $skill)$x[$skill]=$choice(ucfirst($skill),$f['options']);break;
        case 'support':$x['#type']='fieldset';foreach(Questionnaire::SUPPORT as $key=>$label)$x[$key]=$choice($label,$f['options']);break;
        case 'slider':$x['#type']='fieldset';$x['answered']=['#type'=>'checkbox','#title'=>'Provide a slider answer (otherwise unanswered)'];$x['value']=['#type'=>'range','#title'=>'Policy support, 0 strongly oppose, 50 neutral, 100 strongly support','#min'=>0,'#max'=>100,'#step'=>1,'#default_value'=>50,'#attributes'=>['list'=>'lit-policy-ticks']];$x['ticks']=['#type'=>'html_tag','#tag'=>'datalist','#attributes'=>['id'=>'lit-policy-ticks']];foreach(range(0,100,10) as $tick)$x['ticks']['v'.$tick]=['#type'=>'html_tag','#tag'=>'option','#attributes'=>['value'=>$tick,'label'=>(string)$tick]];$x['output']=['#type'=>'html_tag','#tag'=>'output','#attributes'=>['data-policy-output'=>'','aria-live'=>'polite'],'#value'=>'Unanswered (slider position 50)'];break;
        default:$x+=['#type'=>'textfield','#maxlength'=>254];
      }
      if($k==='q02')$x['#description'].=' Enter city/community and state, plus your county below. Whippany and Cedar Knolls, NJ resolve to Hanover Township in Morris County; public counts remain municipality-wide. Your original place entry stays private.';
      if($k==='q02')$x['#states']=['visible'=>[':input[name="questions[q01]"]'=>['value'=>'no']]];
      if(in_array($k,['q26','q27']))$x['#states']=['visible'=>[':input[name="questions[q25]"]'=>['value'=>'yes']]];
      if($k==='q29')$x['#states']=['visible'=>[':input[name="questions[q28]"]'=>['value'=>'yes']]];
      if(in_array($k,['q11','q12','q13','q14'])){$slot=in_array($k,['q11','q13'])?1:2;$x['#states']=['visible'=>[':input[name="questions[q07]['.$slot.'][text]"]'=>['filled'=>TRUE]]];}
      $sections=['q01'=>'Residence & background','q07'=>'Language profile and use','q13'=>'Identity','q15'=>'Language brokering','q21'=>'Language policy','q25'=>'Education','q28'=>'Follow-up'];if(isset($sections[$k]))$form['questions']['section_'.$k]=['#type'=>'html_tag','#tag'=>'h2','#value'=>$sections[$k]];
      $form['questions'][$k]=$x;
    }
    $form['submit']=['#type'=>'submit','#value'=>'Submit local demonstration — no raffle entry'];return $form;
  }
  private function answers(FormStateInterface $state):array {
    $q=$state->getValue('questions');foreach(Questionnaire::catalog() as $f){$k=$f['key'];$x=$q[$k]??NULL;
      if(in_array($f['type'],['age','years']))$q[$k]=$x==='' || $x===NULL?NULL:(int)$x;
      elseif($f['type']==='multiple')$q[$k]=array_values(array_filter($x??[],fn($v)=>$v!==0 && $v!=='0'))?:NULL;
      elseif($f['type']==='slider')$q[$k]=!empty($x['answered'])?(int)$x['value']:NULL;
      elseif($f['type']==='languages'){foreach($q[$k] as &$slot)$slot['age']=$slot['age']==='' || $slot['age']===NULL?NULL:(int)$slot['age'];unset($slot);}
      elseif(in_array($f['type'],['frequency','proficiency','support']))$q[$k]=array_map(fn($v)=>$v===''?NULL:$v,$x);
      elseif($f['type']==='gender')$q[$k]['choice']=$x['choice']===''?NULL:$x['choice'];
      elseif($f['type']!=='contact')$q[$k]=$x===''?NULL:$x;
    }
    return ['consent'=>(bool)$state->getValue('consent'),'map_permission'=>$state->getValue(['application','map_permission']),'questions'=>$q,'resolutions'=>[]];
  }
  public function validateForm(array &$form,FormStateInterface $state):void {
    $ip=$this->getRequest()->getClientIp()??'unknown';$flood=\Drupal::flood();
    if(time()-(int)$this->getRequest()->getSession()->get('lit_demo_opened',0)>3600 || !$flood->isAllowed('lit.demo.submit',20,3600,$ip) || $state->getValue('website') || !\Drupal::csrfToken()->validate((string)$state->getValue('demo_token'),'lit.local.demo')){$state->setErrorByName('consent','Unable to accept this demonstration. Reload the page or try again later.');return;}$flood->register('lit.demo.submit',3600,$ip);
    try{$now=gmdate('Y-m-d\TH:i:s+00:00');\Drupal::service('lit_contribution.validator')->normalize(['schema_version'=>2,'survey_id'=>'LIT_LOCAL_QUESTIONNAIRE_V2','response_id'=>'VALIDATION_ONLY','email'=>(string)$state->getValue(['application','email']),'submitted_at'=>$now,'updated_at'=>$now,'answers'=>$this->answers($state)],FALSE);}catch(\Throwable $e){$state->setErrorByName('questions','Check the questionnaire values and the consent/18+ gate. Optional fields may be left unanswered.');}
  }
  public function submitForm(array &$form,FormStateInterface $state):void {
    try{\Drupal::service('lit_contribution.local_source')->submit($this->answers($state),(string)$state->getValue(['application','email']));$this->messenger()->addStatus('Local demonstration received for private review. No real raffle entry. Nothing is published before approval and owner publication.');$state->setRedirect('lit_contribution.demo');}
    catch(\Throwable $e){$this->messenger()->addError('Unable to accept this demonstration response. It may already have been received; otherwise check your entries or try again later.');}
    $state->disableCache();
  }
}
