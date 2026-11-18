<?php
namespace Drupal\lit_map\Plugin\Validation\Constraint;
use Symfony\Component\Validator\{Constraint,ConstraintValidator};
use Drupal\lit_map\Service\EditorialResources;
final class ResourceConstraintValidator extends ConstraintValidator {
  public function validate($entity, Constraint $constraint): void {
    if($entity->bundle()!=='lit_resource')return;
    $url=$entity->field_lit_external->uri;$media=!$entity->field_lit_media->isEmpty();
    foreach(['field_lit_external','field_lit_transcript'] as $field)if($entity->get($field)->uri && !EditorialResources::safeUrl($entity->get($field)->uri))$this->context->buildViolation('Use an HTTPS URL without credentials.')->atPath($field)->addViolation();
    if(($url && $media) || ($entity->isPublished() && !$url && !$media))$this->context->buildViolation('Published resources need exactly one external link or Media reference. Draft placeholders may have neither.')->atPath('field_lit_external')->addViolation();
    $town=$entity->field_lit_municipality->value;
    if($town){$county=$entity->field_lit_counties->entity?->field_lit_geoid->value;$path=\Drupal::service('extension.list.module')->getPath('lit_map');$towns=array_column(json_decode(file_get_contents($path.'/data/survey-statewide-references.json'),TRUE)['municipalities'],NULL,'id');if(!isset($towns[$town]) || $towns[$town]['county']!==$county)$this->context->buildViolation('Municipality must belong to the referenced county.')->atPath('field_lit_municipality')->addViolation();}
  }
}
