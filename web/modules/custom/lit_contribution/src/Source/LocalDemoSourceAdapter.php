<?php
namespace Drupal\lit_contribution\Source;
use Drupal\lit_contribution\Service\ExportImporter;
/** Local-only collection boundary; shares the private operational snapshot/reviews. */
final class LocalDemoSourceAdapter {
  public function __construct(private readonly ExportImporter $importer){}
  public function submit(array $answers,string $duplicateCheckEmail):array {return $this->importer->local($answers,$duplicateCheckEmail);}
}
