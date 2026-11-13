<?php

namespace Drupal\lit_map\Service;

use Drupal\Core\Extension\ModuleExtensionList;
use Psr\Log\LoggerInterface;

/** Combines public metrics with validated, module-owned geometry metadata. */
final class BubblePreviewData {

  public function __construct(
    private readonly SurveyDataProvider $survey,
    private readonly ModuleExtensionList $modules,
    private readonly LoggerInterface $logger,
  ) {}

  /** Retains only the numeric geometry fields the browser needs. */
  private function feature(mixed $feature): array {
    if (!is_array($feature)
      || !is_array($feature['anchor'] ?? NULL) || !array_is_list($feature['anchor']) || count($feature['anchor']) !== 2
      || !is_array($feature['bbox'] ?? NULL) || !array_is_list($feature['bbox']) || count($feature['bbox']) !== 4
      || !isset($feature['clearance'])) {
      throw new \UnexpectedValueException();
    }
    foreach (array_merge($feature['anchor'], $feature['bbox'], [$feature['clearance']]) as $number) {
      if ((!is_int($number) && !is_float($number)) || !is_finite((float) $number)) {
        throw new \UnexpectedValueException();
      }
    }
    [$x, $y, $width, $height] = $feature['bbox'];
    if ($width <= 0 || $height <= 0 || $feature['clearance'] < 0
      || $feature['anchor'][0] < $x || $feature['anchor'][0] > $x + $width
      || $feature['anchor'][1] < $y || $feature['anchor'][1] > $y + $height) {
      throw new \UnexpectedValueException();
    }
    return array_intersect_key($feature, array_flip(['anchor', 'bbox', 'clearance']));
  }

  public function load(): ?array {
    $data = $this->survey->loadStatewide();
    if ($data === NULL) {
      return NULL;
    }
    try {
      $path = $this->modules->getPath('lit_map');
      $content = @file_get_contents($path . '/data/bubble-geography.json');
      if ($content === FALSE) {
        throw new \RuntimeException();
      }
      $geo = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($geo) || !is_array($geo['counties'] ?? NULL)
        || !is_array($geo['municipalities'] ?? NULL) || !is_array($geo['assets'] ?? NULL)
        || count($geo['counties']) !== 21 || count($geo['assets']) !== 21
        || count($geo['municipalities']) !== count($data['municipalities'])) {
        throw new \UnexpectedValueException();
      }
      $public = ['counties' => [], 'municipalities' => [], 'assets' => []];
      foreach ($data['counties'] as $county) {
        $id = $county['geoid'];
        $public['counties'][$id] = $this->feature($geo['counties'][$id] ?? NULL);
        $asset = $geo['assets'][$id] ?? NULL;
        if (!is_string($asset) || !preg_match('/^municipalities\/' . $id . '\.[a-f0-9]{12}\.svg$/D', $asset)) {
          throw new \UnexpectedValueException();
        }
        $public['assets'][$id] = $asset;
      }
      foreach ($data['municipalities'] as $town) {
        $feature = $geo['municipalities'][$town['id']] ?? NULL;
        if (!is_array($feature) || ($feature['county'] ?? NULL) !== $town['county']
          || ($feature['name'] ?? NULL) !== $town['name'] || 'm' . ($feature['geoid'] ?? '') !== $town['id']) {
          throw new \UnexpectedValueException();
        }
        $public['municipalities'][$town['id']] = $this->feature($feature);
      }
      if (!is_string($geo['usaAsset'] ?? NULL) || !preg_match('/^us-states-2025\.[a-f0-9]{12}\.svg$/D', $geo['usaAsset'])) {
        throw new \UnexpectedValueException();
      }
      $public['usaAsset'] = $geo['usaAsset'];
      return ['survey' => $data, 'geography' => $public, 'assetBase' => base_path() . $path . '/assets/'];
    }
    catch (\Throwable $error) {
      $this->logger->error('Bubble preview geography unavailable. Error category: @type.', ['@type' => get_class($error)]);
      return NULL;
    }
  }

}
