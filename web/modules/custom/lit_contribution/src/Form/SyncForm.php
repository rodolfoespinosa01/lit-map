<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
final class SyncForm extends ConfirmFormBase {
  public function getFormId(): string { return 'lit_contribution_sync'; }
  public function getQuestion() { return $this->t('Synchronize the complete local mock source?'); }
  public function getDescription() { return $this->t('No Qualtrics API is contacted. New or changed responses become pending. Conflicting email groups require resolution. Public counts do not change until publication.'); }
  public function getCancelUrl(): Url { return Url::fromRoute('lit_contribution.list'); }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $counts=\Drupal::service('lit_contribution.reviews')->sync();
      $this->messenger()->addStatus($this->t('Synchronized: @new new, @changed changed, @removed removed, @unchanged unchanged.',['@new'=>$counts['new'],'@changed'=>$counts['changed'],'@removed'=>$counts['removed'],'@unchanged'=>$counts['unchanged']]));
    } catch (\Throwable $e) { $this->getLogger('lit_contribution')->error('Synchronization failed: @type',['@type'=>get_class($e)]);$this->messenger()->addError($this->t('Synchronization failed; prior metadata was preserved. Check the mock source and retry.')); }
    $form_state->setRedirect('lit_contribution.list');
  }
}
