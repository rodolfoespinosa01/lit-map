<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\lit_contribution\Service\OwnerAccess;
final class AdministratorsForm extends FormBase {
  use AssignedPasswordTrait;
  public function getFormId(): string { return 'lit_contribution_administrators'; }
  public function buildForm(array $form,FormStateInterface $form_state): array {
    $form_state->disableCache();$form['#cache']['max-age']=0;
    $owner=\Drupal::service('lit_contribution.owner');$owner->requireOwner();
    $u=User::load($owner->ownerId());
    $form['owner']=['#type'=>'item','#title'=>'Sole application owner','#plain_text'=>$u?$u->getAccountName().' · '.$u->getEmail():'Not bootstrapped; use the local setup command.'];
    $form['notice']=['#plain_text'=>'Superadmin assigns administrator emails and passwords. These authorize internal Drupal account records; administrators do not register, choose passwords or accept email invitations. Deactivation revokes sessions. Only the superadmin can publish, change settings or manage accounts.'];
    $form['email']=['#type'=>'email','#title'=>'Administrator email','#maxlength'=>254];
    $this->passwordFields($form);
    $form['add']=['#type'=>'submit','#value'=>'Add administrator','#name'=>'add'];
    $form['accounts']=['#type'=>'table','#header'=>['Account email','Status','Password','Action']];
    $ids=\Drupal::entityQuery('user')->accessCheck(FALSE)->condition('roles',[OwnerAccess::ADMIN_ROLE,'lit_contribution_reviewer'],'IN')->sort('uid')->execute();
    foreach(User::loadMultiple($ids) as $a){
      $id=$a->id();$form['accounts'][$id]['mail']=['#plain_text'=>$a->getEmail()];$form['accounts'][$id]['status']=['#plain_text'=>($a->isActive()?'Active':'Inactive').($a->hasRole(OwnerAccess::ADMIN_ROLE)?'':' · legacy reviewer; explicit server authorization required for LiT password login')];
      $form['accounts'][$id]['password']=['#plain_text'=>'Not available'];
      if($a->hasRole(OwnerAccess::ADMIN_ROLE) && !in_array((int)$id,[1,$owner->ownerId()],TRUE))$form['accounts'][$id]['password']=['#type'=>'link','#title'=>'Reset password','#url'=>\Drupal\Core\Url::fromRoute('lit_contribution.administrator_password',['administrator'=>(int)$id])];
      if((int)$id!==$owner->ownerId() && (int)$id!==1)$form['accounts'][$id]['action']=['#type'=>'submit','#value'=>$a->isActive()?'Deactivate':'Reactivate','#name'=>'toggle_'.$id,'#target_uid'=>(int)$id,'#activate'=>!$a->isActive(),'#limit_validation_errors'=>[],'#submit'=>['::submitForm'],'#attributes'=>['formnovalidate'=>'formnovalidate']];
    }
    $form['audit']=['#type'=>'table','#caption'=>'Access changes (latest 50)','#header'=>['Actor UID','Target UID','Action','UTC time'],'#rows'=>[]];
    foreach(\Drupal::database()->select('lit_contribution_access_audit','a')->fields('a')->orderBy('id','DESC')->range(0,50)->execute() as $a)$form['audit']['#rows'][]=[$a->actor_uid,$a->target_uid,$a->action,gmdate('Y-m-d H:i:s',$a->occurred_at)];
    return $form;
  }
  public function validateForm(array &$form,FormStateInterface $form_state): void {
    if(isset($form_state->getTriggeringElement()['#target_uid']))return;
    try{\Drupal\lit_contribution\Service\PasswordPolicy::validate((string)$form_state->getValue('password'));}catch(\InvalidArgumentException $e){$form_state->setErrorByName('password',$e->getMessage());}
  }
  public function submitForm(array &$form,FormStateInterface $form_state): void {
    $owner=\Drupal::service('lit_contribution.owner');$owner->requireOwner();$button=$form_state->getTriggeringElement();
    try {
      if(isset($button['#target_uid']))$owner->setActive($button['#target_uid'],$button['#activate']);
      else $owner->add((string)$form_state->getValue('email'),(string)$form_state->getValue('password'));
      $this->messenger()->addStatus('Administrator access updated.');
    }catch(\InvalidArgumentException|\RuntimeException $e){$this->messenger()->addError($e->getMessage());}
    $this->forgetPassword($form_state);
    $form_state->setRedirect('lit_contribution.administrators');
  }
}
