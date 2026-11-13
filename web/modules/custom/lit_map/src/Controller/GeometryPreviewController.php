<?php

namespace Drupal\lit_map\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Displays the static geometry asset, without survey data or entities.
 */
final class GeometryPreviewController extends ControllerBase {

  /**
   * Returns a normal Drupal render array and attached CSS library.
   */
  public function build(): array {
    // This fixed, versioned file is generated from Census attributes, never input.
    $metadata = json_decode(file_get_contents(__DIR__ . '/../../data/nj-counties.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    return [
      '#theme' => 'lit_map_geometry_preview',
      '#counties' => $metadata['counties'],
      '#attached' => ['library' => ['lit_map/geometry_preview']],
      '#cache' => ['max-age' => \Drupal\Core\Cache\Cache::PERMANENT],
    ];
  }

}
