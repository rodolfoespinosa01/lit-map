<?php
namespace Drupal\lit_contribution\Service;

/** Pure eligibility/aggregation shared by publication, reconciliation and tests. */
final class AggregateBuilder {
  public static function exclusions(?array $source, array $row): array {
    return [
      'absent'=>!$source,
      'pending'=>$row['status']==='pending',
      'rejected'=>$row['status']==='rejected',
      'duplicate'=>(bool)($row['duplicate_conflict'] || ($row['duplicate_choice'] && (int)$row['duplicate_choice']!==(int)$row['id'])),
      'permission'=>$source && $source['answers']['aggregate_permission']!=='yes',
      'not_approved'=>$row['status']!=='approved',
    ];
  }
  public static function build(array $snapshot, array $rows): array {
    $included=[];$buckets=[];$groups=[];$dailyRecords=[];$dailyGroups=[];$cohorts=[];
    foreach($rows as $row) {
      $s=$snapshot[$row['survey_id'].'/'.$row['response_id']]??NULL;
      if(in_array(TRUE,self::exclusions($s,$row),TRUE)) continue;
      $included[]=(int)$row['id'];$a=$s['answers'];
      $positive=array_keys(array_filter($a['languages'],fn($p)=>$p['uses_language']==='yes'));sort($positive,SORT_STRING);
      $combination=$a['county_geoid'].'/'.($a['municipality']??'_unspecified').'/'.implode(',',$positive);
      $groups[$combination]??=['county_geoid'=>$a['county_geoid'],'municipality'=>$a['municipality'],'languages'=>$positive,'response_count'=>0];
      $groups[$combination]['response_count']++;
      $day=SubmissionDates::day($s['submitted_at']??NULL);$dk=($day??'_undated').'/'.$combination;
      $dailyGroups[$dk]??=['day'=>$day,'county_geoid'=>$a['county_geoid'],'municipality'=>$a['municipality'],'languages'=>$positive,'response_count'=>0];$dailyGroups[$dk]['response_count']++;
      $cohorts[$dk]??=['day'=>$day,'county_geoid'=>$a['county_geoid'],'municipality'=>$a['municipality'],'languages'=>$positive];QuestionPanels::add($cohorts[$dk],$s);

      foreach($a['languages'] as $language=>$profile) {
        if($profile['uses_language']==='prefer_not_to_answer') continue;
        $key=$a['county_geoid'].'/'.($a['municipality']??'_unspecified').'/'.$language;
        $buckets[$key]??=['county_geoid'=>$a['county_geoid'],'municipality'=>$a['municipality'],'language'=>$language,'response_count'=>0];
        $buckets[$key]['response_count']+=(int)($profile['uses_language']==='yes');
        $dr=($day??'_undated').'/'.$key;$dailyRecords[$dr]??=['day'=>$day,'county_geoid'=>$a['county_geoid'],'municipality'=>$a['municipality'],'language'=>$language,'response_count'=>0];$dailyRecords[$dr]['response_count']+=(int)($profile['uses_language']==='yes');
      }
    }
    ksort($buckets);ksort($groups);sort($included);
    ksort($dailyRecords);ksort($dailyGroups);ksort($cohorts);return ['included'=>$included,'questionnaire'=>array_values($cohorts),'aggregate'=>['schema_version'=>4,'synthetic'=>TRUE,'records'=>array_values($buckets),'groups'=>array_values($groups),'daily_records'=>array_values($dailyRecords),'daily_groups'=>array_values($dailyGroups)]];
  }
}
