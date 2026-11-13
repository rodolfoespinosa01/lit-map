<?php

namespace Drupal\lit_map\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\lit_map\Service\SurveyDataProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hosts the synthetic interaction preview, without preparing survey data.
 */
final class InteractionPreviewController extends ControllerBase {

  public function __construct(private readonly SurveyDataProvider $surveyData) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('lit_map.survey_data'));
  }

  public function build(): array {
    $data = $this->surveyData->load();
    $build = [
      '#theme' => 'lit_map_interaction_preview',
      '#available' => $data !== NULL,
      '#attached' => ['library' => ['lit_map/interactions']],
      // Together with route no_cache: TRUE, disables render and page caching.
      '#cache' => ['max-age' => 0],
    ];
    if ($data !== NULL) {
      $build['#attached']['drupalSettings']['litMapPreview'] = $data;
    }
    return $build;
  }

}
