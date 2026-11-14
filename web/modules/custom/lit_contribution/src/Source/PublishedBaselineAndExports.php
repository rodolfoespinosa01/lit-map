<?php
namespace Drupal\lit_contribution\Source;
use Drupal\lit_contribution\Service\{ExportStore,SubmissionValidator};

/** Explicit publication composition: exactly one baseline plus cumulative uploads. */
final class PublishedBaselineAndExports implements SubmissionSourceInterface {
  public function __construct(private readonly MockQualtricsSource $mock,private readonly ExportStore $store,private readonly SubmissionValidator $validator){}
  public static function baseline(string $survey,string $response):bool {
    return $survey===self::baselineSurvey() && preg_match('/^'.(self::baselineSurvey()===MockQualtricsSource::LARGE?'R_LARGE_':'R_QV2_').'\d{5}$/D',$response) && (int)substr($response,-5)>=1 && (int)substr($response,-5)<=10000;
  }
  public static function baselineSurvey():string {return \Drupal::state()->get('lit_contribution.baseline',MockQualtricsSource::LARGE);}
  public static function reserved(string $survey):bool {return str_starts_with($survey,'SYN_LIT_') || $survey===MockQualtricsSource::LEGACY;}
  public function listSubmissions():array {
    $baseline=array_filter($this->mock->load(self::baselineSurvey()),fn($s)=>self::baseline($s['survey_id'],$s['response_id']));
    if(count($baseline)!==10000)throw new \RuntimeException('The required 10,000-response baseline is incomplete.');
    $ops=[];foreach($this->store->snapshot() as $key=>$raw){$s=$this->validator->normalize($raw,FALSE);if(self::reserved($s['survey_id']) || $key!==$s['survey_id'].'/'.$s['response_id'])throw new \RuntimeException('Invalid operational source reference.');$ops[$key]=$s;}
    return $baseline+$ops;
  }
  public function getSubmission(string $surveyId,string $responseId):?array {
    if(self::baseline($surveyId,$responseId))return $this->mock->load(self::baselineSurvey())[$surveyId.'/'.$responseId]??NULL;
    return $this->store->snapshot()[$surveyId.'/'.$responseId]??NULL;
  }
}
