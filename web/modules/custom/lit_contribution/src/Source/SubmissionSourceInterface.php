<?php
namespace Drupal\lit_contribution\Source;

/** Private authoritative source boundary; no browser endpoint implements this. */
interface SubmissionSourceInterface {
  /** Complete, consistent snapshot. Throw on partial results or source failure. */
  public function listSubmissions(): array;
  /** Return current normalized submission, or NULL only for confirmed absence. */
  public function getSubmission(string $surveyId, string $responseId): ?array;
}
