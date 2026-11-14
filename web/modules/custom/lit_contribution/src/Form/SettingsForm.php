<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lit_contribution\Service\Invitation;
final class SettingsForm extends ConfigFormBase {
  public function getFormId(): string { return 'lit_contribution_settings'; }
  protected function getEditableConfigNames(): array { return ['lit_contribution.settings']; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $c=$this->config('lit_contribution.settings');
    $form['notice']=['#plain_text'=>'Off hides the invitation. Local demo requires the explicit development settings flag and DDEV; no real raffle entry. External mode links to your configured Qualtrics survey; disabling it does not close Qualtrics. The panel threshold is a development default, not research-team disclosure approval.'];
    $form['contribution_mode']=['#type'=>'select','#title'=>'Contribution mode','#options'=>['off'=>'Off','local'=>'Local demo form','external'=>'External survey URL'],'#default_value'=>\Drupal::service('lit_contribution.invitation')->mode()];
    $form['panel_threshold']=['#type'=>'number','#title'=>'Minimum answered responses for questionnaire panels','#min'=>2,'#max'=>100,'#default_value'=>$c->get('panel_threshold')??5];
    $form['survey_url']=['#type'=>'url','#title'=>$this->t('Configured Qualtrics survey URL'),'#default_value'=>$c->get('survey_url'),'#description'=>'HTTPS, a subdomain of qualtrics.com, and /jfe/form/SV_… only. No credentials, query parameters or fragments. The URL is validated syntactically, not contacted.'];
    return parent::buildForm($form,$form_state);
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if($form_state->getValue('contribution_mode')==='local' && !\Drupal::service('lit_contribution.invitation')->development())$form_state->setErrorByName('contribution_mode','Local demo is disabled by deployment configuration.');
    parent::validateForm($form,$form_state);$url=trim((string)$form_state->getValue('survey_url'));$form_state->setValue('survey_url',$url);
    if (($url!=='' || $form_state->getValue('contribution_mode')==='external') && !Invitation::validUrl($url)) $form_state->setErrorByName('survey_url',$this->t('Enter a valid HTTPS Qualtrics survey URL before enabling the invitation.'));
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('lit_contribution.settings')->set('contribution_mode',$form_state->getValue('contribution_mode'))->set('panel_threshold',(int)$form_state->getValue('panel_threshold'))->set('invitation_enabled',$form_state->getValue('contribution_mode')==='external')->set('survey_url',$form_state->getValue('survey_url'))->save();
    parent::submitForm($form,$form_state);
  }
}
