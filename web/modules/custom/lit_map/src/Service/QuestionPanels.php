<?php
namespace Drupal\lit_map\Service;
/** Private aggregate cells only; public responses are thresholded after filtering. */
final class QuestionPanels {
  public static function definitions():array {
    $d=[];$catalog=array_column(Questionnaire::catalog(),NULL,'key');
    foreach(['q09','q15','q16','q17','q19','q21','q22','q23','q24','q25','q26','q27'] as $k)$d[$k]=['label'=>$catalog[$k]['label'],'options'=>$catalog[$k]['options']??[],'language'=>NULL];
    foreach(['q22'=>100,'q26'=>120] as $key=>$max)$d[$key]['options']=array_map('strval',range(0,$max));
    foreach(SubmissionValidator::languages() as $l=>$name){$d['frequency.'.$l]=['label'=>$name.' — use frequency','options'=>$catalog['q08']['options'],'language'=>$l];foreach(Questionnaire::SKILLS as $skill)$d['proficiency.'.$l.'.'.$skill]=['label'=>$name.' — '.$skill.' proficiency (slot-specific questionnaire options)','options'=>$catalog['q11']['options'],'language'=>$l];foreach(Questionnaire::SUPPORT as $key=>$label)$d['support.'.$l.'.'.$key]=['label'=>$name.' — support: '.$label,'options'=>$catalog['q13']['options'],'language'=>$l];}return $d;
  }
  public static function values(array $s):array {
    if(($s['schema_version']??1)!==2)return [];$q=$s['answers']['questions'];$out=[];
    foreach(['q09','q15','q16','q17','q19','q21','q22','q23','q24','q25','q26','q27'] as $k){if(in_array($k,['q26','q27']) && $q['q25']!=='yes')continue;$x=$q[$k];$out[$k]=$x===NULL?NULL:(is_array($x)?$x:[(string)$x]);}
    foreach($s['answers']['slot_languages'] as $i=>$l){if(!$l)continue;$out['frequency.'.$l]=$q['q08'][$i]===NULL?NULL:[$q['q08'][$i]];foreach(Questionnaire::SKILLS as $skill){$x=$q['q'.(10+$i)][$skill];$out['proficiency.'.$l.'.'.$skill]=$x===NULL?NULL:[$x];}if($i>0)foreach(Questionnaire::SUPPORT as $key=>$label){$x=$q['q'.(12+$i)][$key];$out['support.'.$l.'.'.$key]=$x===NULL?NULL:[$x];}}return $out;
  }
  public static function add(array &$cohort,array $s):void {
    $cohort['total']=($cohort['total']??0)+1;$cohort['legacy']=($cohort['legacy']??0)+(int)(($s['schema_version']??1)!==2);$cohort['questions']??=[];
    foreach(self::values($s) as $k=>$values){$p=&$cohort['questions'][$k];$p??=['eligible'=>0,'answered'=>0,'values'=>[]];$p['eligible']++;if($values!==NULL){$p['answered']++;foreach($values as $value)$p['values'][(string)$value]=($p['values'][(string)$value]??0)+1;}unset($p);}
  }
  public static function filter(array $cohorts,array $languages,string $county='',string $municipality='',string $from='',string $through='',int $threshold=5):array {
    SubmissionDates::bounds($from,$through);$threshold=max(2,min(100,$threshold));$questions=[];$total=$legacy=0;
    foreach($cohorts as $c){if(!$languages || !array_intersect($languages,$c['languages']) || ($county!=='' && $county!==$c['county_geoid']) || ($municipality!=='' && $municipality!==($c['municipality']??'_unspecified')) || !SubmissionDates::includes($c['day'],$from,$through))continue;
      $total+=$c['total'];$legacy+=$c['legacy'];foreach($c['questions'] as $k=>$p){$questions[$k]??=['eligible'=>0,'answered'=>0,'values'=>[]];foreach(['eligible','answered'] as $n)$questions[$k][$n]+=$p[$n];foreach($p['values'] as $v=>$n)$questions[$k]['values'][$v]=($questions[$k]['values'][$v]??0)+$n;}
    }
    $out=[];foreach(self::definitions() as $k=>$d){if($d['language'] && !in_array($d['language'],$languages,TRUE))continue;$p=$questions[$k]??['eligible'=>0,'answered'=>0,'values'=>[]];if(!$p['eligible'])continue;$missing=$p['eligible']-$p['answered'];$small=$p['answered']<$threshold || ($missing>0 && $missing<$threshold);foreach($p['values'] as $n)if($n<$threshold)$small=TRUE;
      $panel=['key'=>$k,'label'=>$d['label'],'suppressed'=>$small];if(!$small){$panel+=['eligible'=>$p['eligible'],'answered'=>$p['answered'],'missing'=>$missing,'values'=>[]];foreach($p['values'] as $v=>$n)$panel['values'][]=['label'=>$d['options'][$v]??(string)$v,'count'=>$n];} $out[]=$panel;
    }
    return ['threshold'=>$threshold,'panels'=>$out,'legacyWithoutQuestionnaire'=>$legacy,'matchingResponses'=>$total,'notice'=>'Development disclosure default, not research-team approval. A whole panel is suppressed if answered or any nonzero answer/missing cell is below the threshold. Multi-choice counts overlap. Applicability follows documented demo assumptions; legacy responses have no invented questionnaire answers.'];
  }
}
