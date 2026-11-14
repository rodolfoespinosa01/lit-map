<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\{FormBase,FormStateInterface};
use Drupal\lit_contribution\Service\SubmissionDates;
final class ReviewDateFilterForm extends FormBase {
 public function getFormId():string{return 'lit_review_dates';}
 public function buildForm(array $form,FormStateInterface $state):array {
  $form['notice']=['#plain_text'=>'Filter by submitted date, inclusive America/New_York calendar days. Submitted timestamps retain their source offsets; imported/reviewed/published timestamps are UTC. Undated records appear only in All time.'];
  foreach(['submitted_from'=>'From','submitted_through'=>'Through'] as $k=>$label)$form[$k]=['#type'=>'date','#title'=>$label,'#default_value'=>$this->getRequest()->query->get($k,'')];
  $form['apply']=['#type'=>'submit','#value'=>'Apply submission dates'];$form['clear']=['#type'=>'submit','#value'=>'All time','#clear'=>TRUE,'#limit_validation_errors'=>[],'#submit'=>['::submitForm']];
  try{SubmissionDates::bounds((string)$this->getRequest()->query->get('submitted_from',''),(string)$this->getRequest()->query->get('submitted_through',''));}catch(\Throwable $e){$form['invalid']=['#plain_text'=>'Invalid date range. No records shown.'];}return $form;
 }
 public function validateForm(array &$form,FormStateInterface $state):void {try{SubmissionDates::bounds((string)$state->getValue('submitted_from'),(string)$state->getValue('submitted_through'));}catch(\Throwable $e){$state->setErrorByName('submitted_from','Choose valid From/Through dates in order.');}}
 public function submitForm(array &$form,FormStateInterface $state):void {$q=empty($state->getTriggeringElement()['#clear'])?array_filter(['submitted_from'=>$state->getValue('submitted_from'),'submitted_through'=>$state->getValue('submitted_through')]):[];$route=\Drupal::routeMatch()->getRouteName();$state->setRedirect(in_array($route,['lit_contribution.list','lit_contribution.pending'])?$route:'lit_contribution.pending',[],['query'=>$q]);}
}
