<?php

/**
 * Standalone contract tests: ddev exec php tests/lit_map/normalizer-test.php.
 */

require __DIR__ . '/../../web/modules/custom/lit_map/src/Service/SurveyNormalizer.php';

use Drupal\lit_map\Service\SurveyNormalizer;

$directory = __DIR__ . '/../../web/modules/custom/lit_map/data/';
$fixture = json_decode(file_get_contents($directory . 'survey.synthetic.v2.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$counties = json_decode(file_get_contents($directory . 'nj-counties.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$refs = json_decode(file_get_contents($directory . 'survey-references.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$service = new SurveyNormalizer();
$checks = 0;
function check(bool $condition, string $message): void {
  global $checks;
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $checks++;
}
function rejects(callable $change, string $message): void {
  global $service, $fixture, $counties, $refs;
  $input = $fixture;
  $change($input);
  try {
    $service->normalize($input, $counties, $refs);
  }
  catch (InvalidArgumentException $error) {
    check(TRUE, $message);
    return;
  }
  throw new RuntimeException('Accepted invalid fixture: ' . $message);
}

$data = $service->normalize($fixture, $counties, $refs);
$expected = [
  '34003' => ['es' => 20, 'en' => 14, 'ko' => 8],
  '34017' => ['es' => 28, 'en' => 17, 'pt' => 5, 'ko' => 0],
  '34027' => ['es' => 10, 'en' => 16, 'ko' => 0],
  '34013' => ['es' => 26, 'en' => 16, 'pt' => 9],
];
check($data['totals'] === $expected, 'Known per-language county totals');
check($data['breakdown']['34003']['_unspecified']['es'] === 5, 'Bergen unspecified Spanish');
check($data['breakdown']['34017']['_unspecified']['es'] === 6, 'Hudson unspecified Spanish');
check($data['breakdown']['34027']['_unspecified']['es'] === 2, 'Morris unspecified Spanish');
check($data['breakdown']['34013']['_unspecified']['es'] === 4, 'Essex unspecified Spanish');
check($data['totals']['34017']['ko'] === 0 && !array_key_exists('pt', $data['totals']['34027']), 'Explicit zero differs from missing');
check(!array_key_exists('34001', $data['totals']), 'County without records stays absent');
check(!array_key_exists('records', $data) && !array_key_exists('source', $data), 'Only normalized public display data returned');
foreach ($data['totals'] as $county => $values) {
  foreach ($values as $language => $total) {
    $parts = array_column($data['breakdown'][$county], $language);
    check(array_sum($parts) === $total, 'Each county total equals only its municipality buckets');
  }
}
$changed = $fixture;
$changed['records'][0]['response_count'] = 13;
check($service->normalize($changed, $counties, $refs)['totals']['34003']['es'] === 21, 'Normalizer does not cache previous input');
$omitted = $fixture;
unset($omitted['records'][2]['municipality']);
check($service->normalize($omitted, $counties, $refs) === $data, 'Omitted municipality normalizes to unspecified');
$empty = ['schema_version' => 2, 'synthetic' => TRUE, 'records' => []];
check($service->normalize($empty, $counties, $refs)['totals'] === [], 'Empty dataset means missing, not zero');

rejects(function (&$f) { $f['records'][] = $f['records'][0]; }, 'Duplicate municipality combination');
rejects(function (&$f) { $r = $f['records'][2]; unset($r['municipality']); $f['records'][] = $r; }, 'Null and omitted duplicate unspecified combination');
foreach ([-1, 1.5, '12', TRUE, NULL, 9007199254740992] as $value) {
  rejects(function (&$f) use ($value) { $f['records'][0]['response_count'] = $value; }, 'Invalid count type/range');
}
rejects(function (&$f) { unset($f['records'][0]['response_count']); }, 'Missing count');
rejects(function (&$f) { $f['records'][0]['county_geoid'] = '99999'; }, 'Unknown county');
rejects(function (&$f) { $f['records'][0]['county_geoid'] = 34003; }, 'County IDs must be strings');
rejects(function (&$f) { $f['records'][0]['language'] = 'unknown'; }, 'Unknown language');
rejects(function (&$f) { $f['records'][0]['municipality'] = 'unknown'; }, 'Unknown municipality');
rejects(function (&$f) { $f['records'][0]['municipality'] = ''; }, 'Empty municipality is not unspecified');
rejects(function (&$f) { $f['records'][0]['municipality'] = 'newark'; }, 'Municipality county mismatch');
rejects(function (&$f) { $f['records'][0]['email'] = 'not-retained@example.test'; }, 'Unexpected personal fields');
rejects(function (&$f) { $f['county_totals'] = []; }, 'Separate county totals rejected');
rejects(function (&$f) { $f['schema_version'] = 1; }, 'Unsupported schema');
rejects(function (&$f) { $f['synthetic'] = FALSE; }, 'Non-synthetic fixture');
rejects(function (&$f) { $f['records'] = ['invalid' => []]; }, 'Records must be a list');
rejects(function (&$f) { $f['records'][0] = 'invalid'; }, 'Record must be an object');
rejects(function (&$f) { $f['records'][0]['response_count'] = 9007199254740991; }, 'Aggregate overflow rejected');
echo "PASS: $checks validation and aggregation checks.\n";
