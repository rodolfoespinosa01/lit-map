<?php

namespace Drupal\lit_map\Service;

/**
 * Validates the LiT interchange contract, independent of any survey vendor.
 */
final class SurveyNormalizer {

  private const MAX_SAFE_INTEGER = 9007199254740991;

  private function require(bool $condition, string $reason): void {
    if (!$condition) {
      throw new \InvalidArgumentException($reason);
    }
  }

  /**
   * Returns only public labels, references, and per-language response counts.
   */
  public function normalize(array $input, array $countyMetadata, array $references): array {
    $this->require(($input['schema_version'] ?? NULL) === 2 && ($input['synthetic'] ?? NULL) === TRUE, 'Unsupported or non-synthetic dataset.');
    $this->require(!array_diff(array_keys($input), ['schema_version', 'synthetic', 'records']), 'Unexpected dataset fields.');
    $this->require(isset($input['records']) && is_array($input['records']) && array_is_list($input['records']), 'Records must be a list.');
    $counties = [];
    foreach ($countyMetadata['counties'] ?? [] as $county) {
      $this->require(is_string($county['geoid'] ?? NULL) && preg_match('/^34[0-9]{3}$/D', $county['geoid']) === 1 && is_string($county['key'] ?? NULL) && is_string($county['name'] ?? NULL), 'Invalid county reference.');
      $this->require(!isset($counties[$county['geoid']]), 'Duplicate county reference.');
      $counties[$county['geoid']] = array_intersect_key($county, array_flip(['geoid', 'key', 'name']));
    }
    $this->require(count($counties) === 21, 'Incomplete county references.');
    $languages = [];
    foreach ($references['languages'] ?? [] as $language) {
      $id = $language['id'] ?? NULL;
      $this->require(is_string($id) && preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]+)*$/D', $id) === 1 && is_string($language['label'] ?? NULL) && !isset($languages[$id]), 'Invalid language reference.');
      $languages[$id] = ['id' => $id, 'label' => $language['label']];
    }
    $this->require(count($languages) > 0, 'Missing language references.');
    $municipalities = [];
    foreach ($references['municipalities'] ?? [] as $town) {
      $id = $town['id'] ?? NULL;
      $this->require(is_string($id) && preg_match('/^[a-z][a-z0-9-]*$/D', $id) === 1 && is_string($town['name'] ?? NULL) && is_string($town['county'] ?? NULL) && isset($counties[$town['county']]) && !isset($municipalities[$id]), 'Invalid municipality reference.');
      $municipalities[$id] = ['id' => $id, 'name' => $town['name'], 'county' => $town['county']];
    }
    $totals = $breakdown = $seen = [];
    foreach ($input['records'] as $record) {
      $this->require(is_array($record) && !array_diff(array_keys($record), ['county_geoid', 'municipality', 'language', 'response_count']), 'Unexpected record fields.');
      $county = $record['county_geoid'] ?? NULL;
      $language = $record['language'] ?? NULL;
      $town = $record['municipality'] ?? NULL;
      $count = $record['response_count'] ?? NULL;
      $this->require(is_string($county) && isset($counties[$county]), 'Unknown county.');
      $this->require(is_string($language) && isset($languages[$language]), 'Unknown language.');
      $this->require($town === NULL || (is_string($town) && isset($municipalities[$town]) && $municipalities[$town]['county'] === $county), 'Unknown or mismatched municipality.');
      $this->require(is_int($count) && $count >= 0 && $count <= self::MAX_SAFE_INTEGER, 'Invalid response count.');
      $bucket = $town ?? '_unspecified';
      $key = "$county/$bucket/$language";
      $this->require(!isset($seen[$key]), 'Duplicate county/municipality/language combination.');
      $seen[$key] = TRUE;
      $previous = $totals[$county][$language] ?? 0;
      $this->require($count <= self::MAX_SAFE_INTEGER - $previous, 'Response total exceeds safe display range.');
      $totals[$county][$language] = $previous + $count;
      $breakdown[$county][$bucket][$language] = $count;
    }
    return [
      'schemaVersion' => 2,
      'synthetic' => TRUE,
      'counties' => array_values($counties),
      'languages' => array_values($languages),
      'municipalities' => array_values($municipalities),
      'totals' => $totals,
      'breakdown' => $breakdown,
    ];
  }

}
