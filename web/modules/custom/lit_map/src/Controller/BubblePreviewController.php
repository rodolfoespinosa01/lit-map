<?php

namespace Drupal\lit_map\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\lit_map\Service\BubblePreviewData;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class BubblePreviewController extends ControllerBase {

  public function __construct(private readonly BubblePreviewData $preview) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('lit_map.bubble_data'));
  }

  public function build(): array {
    $id = Html::getUniqueId('lit-map-bubbles');
    $data = $this->preview->load();
    $build = [
      '#theme' => 'lit_map_bubble_preview',
      '#instance_id' => $id,
      '#available' => $data !== NULL,
      '#attached' => ['library' => ['lit_map/bubbles']],
      '#cache' => ['max-age' => 0],
    ];
    if ($data !== NULL) {
      $build['#attached']['drupalSettings']['litMapBubbles'][$id] = $data;
    }
    return $build;
  }

}
