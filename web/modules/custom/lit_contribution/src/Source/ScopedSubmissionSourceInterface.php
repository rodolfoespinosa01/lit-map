<?php
namespace Drupal\lit_contribution\Source;
/** One complete survey/dataset snapshot; absence applies only within this scope. */
interface ScopedSubmissionSourceInterface extends SubmissionSourceInterface {
  public function surveyId(): string;
}
