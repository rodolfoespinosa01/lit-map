<?php
namespace Drupal\lit_map\Plugin\Validation\Constraint;
use Symfony\Component\Validator\Constraint;
/**
 * @Constraint(
 *   id = "LitStableIdentifier",
 *   label = @Translation("LiT stable identifier", context = "Validation")
 * )
 */
final class StableIdentifierConstraint extends Constraint {
  public $message = 'The LiT identifier must be valid, unique within its bundle, and cannot change after creation.';
}
