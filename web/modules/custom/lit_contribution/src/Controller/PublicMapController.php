<?php
namespace Drupal\lit_contribution\Controller;
use Drupal\Core\Controller\ControllerBase;
final class PublicMapController extends ControllerBase {
  public function build(): array { return \Drupal::service('lit_map.component')->build('published'); }
}
