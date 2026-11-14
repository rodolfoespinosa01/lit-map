<?php
namespace Drupal\lit_contribution\Form;
trait AssignedPasswordTrait {
  private function passwordFields(array &$form): void {
    $form['password']=['#type'=>'password','#title'=>'Assigned password','#required'=>TRUE,'#maxlength'=>128,'#attributes'=>['autocomplete'=>'new-password','data-assigned-password'=>''],'#description'=>'Use 16–128 characters with letters and digits plus mixed case/symbols or four distinct words. Copy the assigned password privately before saving; existing passwords can never be displayed.'];
    $form['generate']=['#type'=>'html_tag','#tag'=>'button','#value'=>'Generate strong password','#attributes'=>['type'=>'button','data-generate-password'=>'','disabled'=>'disabled']];
    $form['show']=['#type'=>'checkbox','#title'=>'Show newly assigned password (this screen only)','#attributes'=>['data-show-password'=>'']];
    $form['#attached']['library'][]='lit_contribution/assigned_password';
    $form['#attached']['drupalSettings']['litPasswordWords']=json_decode(file_get_contents(__DIR__.'/../../data/password-words.json'),TRUE,512,JSON_THROW_ON_ERROR);
  }
  private function forgetPassword(\Drupal\Core\Form\FormStateInterface $state): void {
    $state->unsetValue('password');$input=$state->getUserInput();unset($input['password']);$state->setUserInput($input);$state->disableCache();
  }
}
