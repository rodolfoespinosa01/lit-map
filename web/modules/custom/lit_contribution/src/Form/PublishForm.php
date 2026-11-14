<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
final class PublishForm extends ConfirmFormBase {
  public function getFormId(): string { return 'lit_contribution_publish'; }
  public function getQuestion() { return $this->t('Replace the local public aggregate snapshot?'); }
  public function getDescription() { return $this->t('Publication explicitly combines the original 10,000 synthetic baseline responses with approved operational uploads; older fixture datasets and seeded pending requests are excluded. Only approved, permitted, present responses without unresolved duplicates contribute. Rejected or removed responses disappear from the next snapshot. No source identifiers, emails or exact response times are published. This is synthetic local publication, not deployment.'); }
  public function getCancelUrl(): Url { return Url::fromRoute('lit_contribution.list'); }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $result=\Drupal::service('lit_contribution.publication')->publish();
      $this->messenger()->addStatus($this->t('Local aggregate published: @included approved/permitted submissions, @records place/language records.',['@included'=>$result['included'],'@records'=>$result['records']]));
    } catch (\Throwable $e) { $this->getLogger('lit_contribution')->error('Publication failed: @type',['@type'=>get_class($e)]);$this->messenger()->addError($this->t('Publication failed. The last good snapshot remains active. Synchronize/review changed responses and retry.')); }
    $form_state->setRedirect('lit_contribution.list');
  }
}
