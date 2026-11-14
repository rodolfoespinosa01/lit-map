<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lit_contribution\Service\PasswordPolicy;
use Drupal\lit_contribution\Service\OwnerAccess;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
final class AdministratorPasswordForm extends FormBase {
  use AssignedPasswordTrait;
  public function getFormId(): string { return 'lit_contribution_administrator_password'; }
  public function buildForm(array $form,FormStateInterface $form_state,?int $administrator=NULL): array {
    $owner=\Drupal::service('lit_contribution.owner');$owner->requireOwner();$u=User::load($administrator);
    if(!$u || !$u->hasRole(OwnerAccess::ADMIN_ROLE) || in_array((int)$u->id(),[1,$owner->ownerId()],TRUE))throw new AccessDeniedHttpException();
    $form_state->disableCache();$form['#cache']['max-age']=0;$form['account']=['#plain_text'=>'Assign a new password for '.$u->getEmail().'. Saving revokes existing sessions. This does not reactivate a blocked account.'];
    $this->passwordFields($form);$form['submit']=['#type'=>'submit','#value'=>'Reset administrator password'];return $form;
  }
  public function validateForm(array &$form,FormStateInterface $form_state): void {
    try{PasswordPolicy::validate((string)$form_state->getValue('password'));}catch(\InvalidArgumentException $e){$form_state->setErrorByName('password',$e->getMessage());}
  }
  public function submitForm(array &$form,FormStateInterface $form_state): void {
    try{\Drupal::service('lit_contribution.owner')->resetPassword((int)\Drupal::routeMatch()->getParameter('administrator'),(string)$form_state->getValue('password'));$this->messenger()->addStatus('Password reset; existing administrator sessions revoked.');}
    finally{$this->forgetPassword($form_state);}
    $form_state->setRedirect('lit_contribution.administrators');
  }
}
