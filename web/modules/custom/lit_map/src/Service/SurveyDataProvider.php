<?php

namespace Drupal\lit_map\Service;

use Drupal\Core\Extension\ModuleExtensionList;
use Psr\Log\LoggerInterface;

/**
 * Reads fixed module-owned files afresh on every request; never accepts a URL.
 */
final class SurveyDataProvider {

  public function __construct(
    private readonly ModuleExtensionList $modules,
    private readonly SurveyNormalizer $normalizer,
    private readonly LoggerInterface $logger,
  ) {}

  private function read(string $filename): array {
    $path = $this->modules->getPath('lit_map') . '/data/' . $filename;
    // Suppress filesystem warnings: expose neither paths nor raw JSON to visitors.
    $contents = @file_get_contents($path);
    if ($contents === FALSE) {
      throw new \RuntimeException('Module data file unavailable.');
    }
    $data = json_decode($contents, TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
      throw new \InvalidArgumentException('Expected a JSON object.');
    }
    return $data;
  }

  public function load(): ?array {
    return $this->loadFiles('survey.synthetic.v2.json', 'survey-references.json');
  }

  /**
   * A second fixed fixture keeps the original preview and interfaces intact.
   */
  public function loadStatewide(): ?array {
    return $this->loadFiles('survey.statewide.v2.json', 'survey-statewide-references.json');
  }

  private function loadFiles(string $fixture, string $references): ?array {
    try {
      return $this->normalizer->normalize(
        $this->read($fixture),
        \Drupal::service('lit_map.references')->counties(),
        ['languages'=>array_map(fn($id,$label)=>compact('id','label'),array_keys(\Drupal::service('lit_map.references')->languages()),array_values(\Drupal::service('lit_map.references')->languages()))]+$this->read($references),
      );
    }
    catch (\Throwable $error) {
      $this->logger->error('Synthetic display data could not be loaded or validated. Error category: @type.', ['@type' => get_class($error)]);
      return NULL;
    }
  }

}
