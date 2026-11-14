<?php
namespace Drupal\lit_contribution\Source;
use Drupal\lit_contribution\Service\SubmissionValidator;

/** Local synthetic fixtures only. No HTTP client, credentials or Qualtrics API. */
final class MockQualtricsSource implements ScopedSubmissionSourceInterface {
  public function __construct(private readonly string $appRoot, private readonly SubmissionValidator $validator) {}
  public const LEGACY='SYN_LIT_PROVISIONAL_V1';
  public const QUESTIONNAIRE='SYN_LIT_QUESTIONNAIRE_V2';
  public const LARGE='SYN_LIT_10000_V1';
  public function surveyId(): string { return \Drupal::state()->get('lit_contribution.active_dataset',self::LEGACY); }
  public function listSubmissions(): array { return $this->load($this->surveyId()); }
  public function load(string $survey): array {
    $paths=[self::QUESTIONNAIRE=>'/private/lit-questionnaire-10000-v2/submissions.json',self::LEGACY=>'/fixtures/lit-contribution/submissions.v1.json',self::LARGE=>'/private/lit-contribution-demo-10000-v1/submissions.json'];
    if(!isset($paths[$survey]))throw new \RuntimeException('Unknown local dataset.');
    $bytes = @file_get_contents(dirname($this->appRoot) . $paths[$survey]);
    if ($bytes === FALSE) throw new \RuntimeException('Mock source unavailable.');
    $document = json_decode($bytes, TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($document) || count($document) !== 3 || array_diff(array_keys($document), ['schema_version', 'synthetic', 'submissions'])
      || !in_array($document['schema_version'],[1,2],TRUE) || $document['synthetic'] !== TRUE || !is_array($document['submissions']) || !array_is_list($document['submissions'])) {
      throw new \UnexpectedValueException('Invalid mock source envelope.');
    }
    $result = [];
    foreach ($document['submissions'] as $raw) {
      $submission = $this->validator->normalize($raw);
      if($submission['survey_id']!==$survey)throw new \UnexpectedValueException('Dataset scope mismatch.');
      $key = $submission['survey_id'] . '/' . $submission['response_id'];
      if (isset($result[$key])) throw new \UnexpectedValueException('Duplicate source identifier.');
      $result[$key] = $submission;
    }
    return $result;
  }
  public function getSubmission(string $surveyId, string $responseId): ?array {
    return $this->load($surveyId)[$surveyId . '/' . $responseId] ?? NULL;
  }
}
