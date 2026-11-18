<?php
namespace Drupal\lit_map\Plugin\Validation\Constraint;
use Symfony\Component\Validator\{Constraint,ConstraintValidator};
final class MapSettingsConstraintValidator extends ConstraintValidator {
  public function validate($entity, Constraint $constraint): void {
    if($entity->bundle()!=='lit_map')return;
    if($entity->field_lit_source->value==='published' && !\Drupal::hasService('lit_contribution.publication'))$this->context->buildViolation('The approved publication source requires LiT contribution. Choose the informational source.')->atPath('field_lit_source')->addViolation();
    $allowed=array_column($entity->field_lit_languages->getValue(),'target_id');
    foreach($entity->field_lit_defaults->getValue() as $item)if($allowed && !in_array($item['target_id'],$allowed))$this->context->buildViolation('Default languages must be included in this map’s language selection.')->atPath('field_lit_defaults')->addViolation();
  }
}
