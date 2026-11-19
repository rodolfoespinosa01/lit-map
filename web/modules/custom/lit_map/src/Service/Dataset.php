<?php
namespace Drupal\lit_map\Service;

use Drupal\lit_map\Source\NormalizedExportMapper;

/** Validated complete-file snapshots; no source responses are persisted. */
final class Dataset {
  public function __construct(private readonly AtomicAggregateWriter $writer, private readonly SubmissionValidator $validator, private readonly SurveyNormalizer $normalizer) {}
  public function import(string $bytes, string $format, bool $synthetic = FALSE): string {
    $rows=(new NormalizedExportMapper())->decode($bytes,$format);$snapshot=[];
    foreach($rows as $row){
      $s=$this->validator->normalize($row,FALSE);
      $key=$s['survey_id'].'/'.$s['response_id'];
      if(isset($snapshot[$key])) {
        if($snapshot[$key]!==$s)throw new \InvalidArgumentException('Conflicting records share a source ID.');
        continue;
      }
      if(($s['answers']['flags']??[])!==[])throw new \InvalidArgumentException('Resolve geography/language mapping before supplying the export.');
      $snapshot[$key]=$s;
    }
    ksort($snapshot);
    $result=AggregateBuilder::build($snapshot,$synthetic);
    return $this->activate(['dataset_version'=>1,'aggregate'=>$result['aggregate'],'cohorts'=>$result['questionnaire']]);
  }
  public function validate(array $document): array {
    if(count($document)!==3 || ($document['dataset_version']??NULL)!==1 || !is_array($document['cohorts']??NULL) || !array_is_list($document['cohorts']))throw new \InvalidArgumentException('Invalid dataset envelope.');
    $survey=$this->normalizer->normalize($document['aggregate'],$this->validator->counties(),$this->validator->references());
    if($survey['schemaVersion']!==4)throw new \InvalidArgumentException('Date/union snapshot required.');
    $definitions=QuestionPanels::definitions();$expected=[];
    foreach($document['aggregate']['daily_groups'] as $g)$expected[json_encode([$g['day'],$g['county_geoid'],$g['municipality'],$g['languages']])]=$g['response_count'];
    foreach($document['cohorts'] as $c){
      if(count($c)!==7 || array_diff(array_keys($c),['day','county_geoid','municipality','languages','total','legacy','questions']))throw new \InvalidArgumentException('Invalid questionnaire cohort.');
      $key=json_encode([$c['day'],$c['county_geoid'],$c['municipality'],$c['languages']]);
      if(!isset($expected[$key]) || $expected[$key]!==$c['total'] || !is_int($c['legacy']) || $c['legacy']<0 || $c['legacy']>$c['total'] || !is_array($c['questions']))throw new \InvalidArgumentException('Cohort counts disagree with map.');
      unset($expected[$key]);
      foreach($c['questions'] as $k=>$p){
        if(!isset($definitions[$k]) || !is_array($p) || count($p)!==3 || array_diff(array_keys($p),['eligible','answered','values']) || !is_int($p['eligible']) || !is_int($p['answered']) || $p['eligible']<0 || $p['eligible']>$c['total'] || $p['answered']<0 || $p['answered']>$p['eligible'] || !is_array($p['values']))throw new \InvalidArgumentException('Invalid questionnaire counts.');
        foreach($p['values'] as $value=>$n)if(!array_key_exists($value,$definitions[$k]['options']) || !is_int($n) || $n<1 || $n>$p['answered'])throw new \InvalidArgumentException('Unexpected questionnaire value.');
      }
    }
    if($expected)throw new \InvalidArgumentException('Missing questionnaire cohorts.');
    return $survey;
  }
  public function activate(array $document): string {
    $this->validate($document);
    $lock=\Drupal::lock();if(!$lock->acquire('lit_map.dataset',120))throw new \RuntimeException('Another dataset replacement is running.');
    try {
      $hash=$this->writer->write($document);
      // One atomic state-row replacement after the immutable file is complete.
      \Drupal::state()->set('lit_map.dataset',['hash'=>$hash,'date'=>gmdate('Y-m-d')]);
      \Drupal::service('cache_tags.invalidator')->invalidateTags(['lit_map:dataset']);
      return $hash;
    } finally {$lock->release('lit_map.dataset');}
  }
  public function document(): ?array {
    $pointer=\Drupal::state()->get('lit_map.dataset');
    return $pointer?$this->writer->read($pointer['hash']):NULL;
  }
  public function publicData(): ?array {
    $pointer=\Drupal::state()->get('lit_map.dataset');if(!$pointer)return NULL;
    $d=$this->writer->read($pointer['hash']);
    return ['hash'=>$pointer['hash'],'date'=>$pointer['date'],'survey'=>$this->validate($d)];
  }
}
