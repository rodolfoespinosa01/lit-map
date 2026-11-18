<?php
namespace Drupal\lit_map\Plugin\Validation\Constraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
final class StableIdentifierConstraintValidator extends ConstraintValidator {
  public function validate($items, Constraint $constraint): void {
    $entity=$items->getEntity();$field=$items->getName();$key=$items->value;
    $pattern=match($field){'field_lit_geoid'=>'/^34[0-9]{3}$/D','field_lit_language_key'=>'/^[a-z]{2,3}(?:-[A-Za-z0-9]+)*$/D',default=>'/^[a-z][a-z0-9-]{0,63}$/D'};
    if(!$key || !preg_match($pattern,$key)){$this->context->addViolation($constraint->message);return;}
    if($entity->bundle()==='lit_counties'){
      $path=\Drupal::service('extension.list.module')->getPath('lit_map');
      $geometry=array_column(json_decode(file_get_contents($path.'/data/nj-counties.json'),TRUE)['counties'],'key','geoid');
      if(($geometry[$entity->field_lit_geoid->value]??NULL)!==$entity->field_lit_map_key->value){$this->context->addViolation('County GEOID and map key must match the supported geometry.');return;}
    }
    $storage=\Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    $query=$storage->getQuery()->accessCheck(FALSE)->condition($entity->getEntityType()->getKey('bundle'),$entity->bundle())->condition($field,$key);
    if(!$entity->isNew())$query->condition($entity->getEntityType()->getKey('id'),$entity->id(),'<>');
    $original=$entity->isNew()?NULL:$storage->loadUnchanged($entity->id());
    if($query->count()->execute() || ($original && $original->get($field)->value!==$key))$this->context->addViolation($constraint->message);
  }
}
