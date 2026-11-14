<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lit_contribution\Service\ReviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class DecisionForm extends FormBase {
  public function __construct(private readonly ReviewManager $reviews) {}
  public static function create(ContainerInterface $c): static { return new static($c->get('lit_contribution.reviews')); }
  public function getFormId(): string { return 'lit_contribution_decision'; }
  public function buildForm(array $form, FormStateInterface $form_state, ?int $review=NULL): array {
    $row=$this->reviews->get($review);$form['#cache']['max-age']=0;
    $form['review']=['#type'=>'value','#value'=>$review];
    // Signed version guard avoids caching raw source/form state on GET.
    $form['version']=['#type'=>'hidden','#default_value'=>$this->reviews->reviewToken($review)];
    $options=$row['status']==='pending'?['approved'=>'Approve','rejected'=>'Reject']:['pending'=>'Return to pending (then review again)'];
    if ($row['duplicate_conflict']) $options=['resolve'=>'Choose this response; reject the other conflicting duplicates','rejected'=>'Reject (does not resolve the duplicate group)'];
    $form['decision']=['#type'=>'select','#title'=>$this->t('Review action'),'#options'=>$options,'#required'=>TRUE];
    $form['reason']=['#type'=>'textarea','#title'=>$this->t('Internal reason'),'#required'=>TRUE,'#maxlength'=>1000,'#description'=>$this->t('Private review metadata. Never shown on the map. Choosing a duplicate does not verify email ownership.')];
    $form['actions']=['#type'=>'actions'];
    $form['actions']['submit']=['#type'=>'submit','#value'=>$this->t('Save review decision'),'#disabled'=>!$row['source_present']];
    return $form;
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $action=$form_state->getValue('decision');$id=(int)$form_state->getValue('review');
      $expected=$this->reviews->verifyReviewToken($id,(string)$form_state->getValue('version'));
      if ($action==='resolve') $this->reviews->resolve($id,$form_state->getValue('reason'),$expected,(string)$form_state->getValue('version'));
      else $this->reviews->decide($id,$action,$form_state->getValue('reason'),$expected,(string)$form_state->getValue('version'));
      $this->messenger()->addStatus($this->t('Review saved. Publish a new aggregate snapshot for the map to reflect this change.'));
      $form_state->setRedirect('lit_contribution.detail',['review'=>$id]);
    } catch (\Throwable $e) { $this->getLogger('lit_contribution')->error('Review failed: @type',['@type'=>get_class($e)]);$this->messenger()->addError($this->t('Review was not saved. Synchronize and reload the current response, resolve any duplicate conflict, and try again.')); }
  }
}
