<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

final class ResourcesForm extends FormBase {
  public function getFormId(): string { return 'lit_contribution_resources'; }
  public function buildForm(array $form,FormStateInterface $form_state): array {
    \Drupal::service('lit_contribution.owner')->requireOwner();
    $form['notice']=['#plain_text'=>'Editorial recordings/resources are separate from survey counts. Only approved entries appear. Use HTTPS links (including Vimeo), or published Drupal Media accessible to anonymous visitors with an accessible canonical page. No module or permission is enabled automatically. Placeholder cards have no working link. Saving this catalogue does not publish or change survey counts.'];
    $form['catalog']=['#type'=>'textarea','#title'=>'Resource catalogue JSON (version 1)','#rows'=>24,'#required'=>TRUE,'#default_value'=>\Drupal::service('lit_contribution.resources')->json(),'#description'=>'Fields: id, approved (boolean), title, description, languages (IDs), county (GEOID or null), municipality (ID or null), kind (audio/video/resource), target (placeholder/external/media), url (HTTPS or null), media_id (integer or null), transcript_url (HTTPS or null), test_only (boolean). A town requires its county. Global and county-wide entries also match selected towns. With no languages selected, all languages at the selected place match. Drafts use approved: false. Media without public access is omitted. See docs/contribution/resources-passwordless.md for examples.'];
    $form['submit']=['#type'=>'submit','#value'=>'Save resource catalogue'];return $form;
  }
  public function validateForm(array &$form,FormStateInterface $form_state): void {
    try{\Drupal::service('lit_contribution.resources')->validate((string)$form_state->getValue('catalog'));}
    catch(\JsonException|\InvalidArgumentException $e){$form_state->setErrorByName('catalog',$e->getMessage());}
  }
  public function submitForm(array &$form,FormStateInterface $form_state): void {
    $owner=\Drupal::service('lit_contribution.owner');$owner->requireOwner();
    \Drupal::configFactory()->getEditable('lit_contribution.resources')->set('catalog_json',(string)$form_state->getValue('catalog'))->save();
    $owner->audit($owner->ownerId(),'resource_catalogue_updated');
    $this->messenger()->addStatus('Resource catalogue saved. Survey publication is unchanged.');
  }
}
