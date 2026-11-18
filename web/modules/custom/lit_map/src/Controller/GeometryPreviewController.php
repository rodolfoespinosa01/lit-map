<?php
namespace Drupal\lit_map\Controller;
use Drupal\Core\Controller\ControllerBase;
final class GeometryPreviewController extends ControllerBase {
  public function build(): array { return \Drupal::service('lit_map.component')->geometry(); }
}
