<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
final class SignInConfirmForm extends FormBase {
  public function getFormId(): string { return 'lit_contribution_signin_confirm'; }
  public function buildForm(array $form,FormStateInterface $form_state): array {
    $form['notice']=['#plain_text'=>'Confirm sign-in using the link from your local mailbox. It expires after 15 minutes and works once.'];
    $form['token']=['#type'=>'hidden','#attributes'=>['data-lit-signin-token'=>'']];
    $form['actions']['submit']=['#type'=>'submit','#value'=>'Confirm sign-in'];
    $form['#attached']['library'][]='lit_contribution/signin';return $form;
  }
  public function submitForm(array &$form,FormStateInterface $form_state): void {
    $u=\Drupal::service('lit_contribution.passwordless')->consume((string)$form_state->getValue('token'),$this->getRequest()->getClientIp()??'unknown');
    // Never permit a destination query to change the post-login redirect.
    $this->getRequest()->query->remove('destination');
    if(!$u){$this->messenger()->addError('This sign-in link is invalid, expired, or already used. Request a new link.');$form_state->setRedirect('lit_contribution.signin');return;}
    user_login_finalize($u);
    $form_state->setRedirect(\Drupal::service('lit_contribution.owner')->isOwner()?'lit_contribution.dashboard':'lit_contribution.pending');
  }
}
