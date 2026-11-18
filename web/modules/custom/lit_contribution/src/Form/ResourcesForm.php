<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\{FormBase,FormStateInterface};
use Drupal\Core\Url;
final class ResourcesForm extends FormBase {
  public function getFormId(): string { return 'lit_contribution_resources'; }
  public function buildForm(array $form,FormStateInterface $form_state): array {
    \Drupal::service('lit_contribution.owner')->requireOwner();
    $form['notice']=['#plain_text'=>'Resources now use native LiT resource content with taxonomy and Media reference fields. The legacy JSON catalogue is retained for explicit migration. Resource publication remains separate from survey publication. The configured owner retains catalogue editing access; other editors use native Drupal content permissions.'];
    $form['add']=['#type'=>'link','#title'=>'Add LiT resource','#url'=>Url::fromRoute('node.add',['node_type'=>'lit_resource'])];
    $storage=\Drupal::entityTypeManager()->getStorage('node');
    foreach($storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->condition('type','lit_resource')->execute()) as $node)if($node->access('update'))$form['resource_'.$node->id()]=$node->toLink($node->label().($node->isPublished()?' (published)':' (draft)'),'edit-form')->toRenderable();
    return $form;
  }
  public function submitForm(array &$form,FormStateInterface $form_state): void {}
}
