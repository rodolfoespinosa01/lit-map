<?php
namespace Drupal\lit_map\Plugin\Validation\Constraint;
use Symfony\Component\Validator\{Constraint,ConstraintValidator};
final class MapSettingsConstraintValidator extends ConstraintValidator {
  public function validate($entity, Constraint $constraint): void {
    if($entity->bundle()!=='lit_map')return;
    if($entity->hasField('field_lit_survey_url') && !$entity->field_lit_survey_url->isEmpty() && !\Drupal\lit_map\Service\EditorialResources::safeUrl($entity->field_lit_survey_url->uri))$this->context->buildViolation('Use an external HTTPS survey URL without credentials.')->atPath('field_lit_survey_url')->addViolation();
    $allowed=array_column($entity->field_lit_languages->getValue(),'target_id');
    foreach($entity->field_lit_defaults->getValue() as $item)if($allowed && !in_array($item['target_id'],$allowed))$this->context->buildViolation('Default languages must be included in this map’s language selection.')->atPath('field_lit_defaults')->addViolation();
  }
}
