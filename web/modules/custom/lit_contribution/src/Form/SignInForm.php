<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
final class SignInForm extends FormBase {
  public function getFormId(): string { return 'lit_contribution_login'; }
  public function buildForm(array $form,FormStateInterface $form_state): array {
    $form_state->disableCache();$form['#cache']['max-age']=0;
    $form['notice']=['#plain_text'=>'Use the email and password assigned by your superadmin. No registration, email link or mail delivery is required. Contact your superadmin for a password reset.'];
    $form['email']=['#type'=>'email','#title'=>'Email address','#required'=>TRUE,'#maxlength'=>254,'#attributes'=>['autocomplete'=>'username']];
    $form['pass']=['#type'=>'password','#title'=>'Password','#required'=>TRUE,'#maxlength'=>128,'#attributes'=>['autocomplete'=>'current-password']];
    $form['actions']['submit']=['#type'=>'submit','#value'=>'Sign in'];return $form;
  }
  public function validateForm(array &$form,FormStateInterface $form_state): void {
    $mode=(string)\Drupal::routeMatch()->getParameter('mode');
    $u=\Drupal::service('lit_contribution.password_login')->authenticate((string)$form_state->getValue('email'),(string)$form_state->getValue('pass'),$this->getRequest()->getClientIp()??'unknown',$mode);
    $form_state->unsetValue('pass');$input=$form_state->getUserInput();unset($input['pass']);$form_state->setUserInput($input);$form_state->disableCache();
    if(!$u){$form_state->setErrorByName('pass','Unable to sign in. Check your credentials or try again later.');return;}
    $form_state->set('authenticated_uid',(int)$u->id());
  }
  public function submitForm(array &$form,FormStateInterface $form_state): void {
    $this->getRequest()->query->remove('destination');
    $u=User::load($form_state->get('authenticated_uid'));
    if(!$u || !\Drupal::service('lit_contribution.owner')->authorized($u))return;
    user_login_finalize($u);
    $form_state->setRedirect(\Drupal::service('lit_contribution.owner')->isOwner()?'lit_contribution.dashboard':'lit_contribution.pending');
  }
}
