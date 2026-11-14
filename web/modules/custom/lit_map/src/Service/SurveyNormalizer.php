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
    if (($input['schema_version']??NULL)===4)return $this->normalizeDaily($input,$countyMetadata,$references);
    if (($input['schema_version']??NULL)===3) return $this->normalizeCombinations($input,$countyMetadata,$references);
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

  /** Exact disjoint groups, retaining marginal records for zero/missing semantics. */
  private function normalizeCombinations(array $input,array $countyMetadata,array $references): array {
    $this->require(count($input)===4 && !array_diff(array_keys($input),['schema_version','synthetic','records','groups']), 'Unexpected combination dataset fields.');
    $this->require(isset($input['groups']) && is_array($input['groups']) && array_is_list($input['groups']), 'Groups must be a list.');
    $public=$this->normalize(['schema_version'=>2,'synthetic'=>$input['synthetic']??NULL,'records'=>$input['records']??NULL],$countyMetadata,$references);
    $counties=array_column($public['counties'],NULL,'geoid');$towns=array_column($public['municipalities'],NULL,'id');$languages=array_column($public['languages'],NULL,'id');
    $seen=[];$positive=[];$groups=[];$areaCounts=[];$total=0;
    foreach($input['groups'] as $g){
      $this->require(is_array($g) && count($g)===4 && !array_diff(array_keys($g),['county_geoid','municipality','languages','response_count']), 'Unexpected combination fields.');
      $c=$g['county_geoid'];$m=$g['municipality'];$ls=$g['languages'];$n=$g['response_count'];
      $this->require(is_string($c) && isset($counties[$c]), 'Unknown group county.');
      $this->require($m===NULL || (is_string($m) && isset($towns[$m]) && $towns[$m]['county']===$c), 'Unknown or mismatched group municipality.');
      $this->require(is_array($ls) && array_is_list($ls), 'Group languages must be a list.');
      foreach($ls as $l)$this->require(is_string($l) && isset($languages[$l]), 'Unknown group language.');
      $canonical=array_values(array_unique($ls));sort($canonical,SORT_STRING);
      $this->require($ls===$canonical, 'Group languages must be unique and sorted.');
      $this->require(is_int($n) && $n>0 && $n<=self::MAX_SAFE_INTEGER-$total, 'Invalid or unsafe group count.');$total+=$n;
      $bucket=$m??'_unspecified';$key=$c.'/'.$bucket.'/'.implode(',',$ls);
      $this->require(!isset($seen[$key]), 'Duplicate geography/language combination.');$seen[$key]=TRUE;
      $areaCounts[$c][$bucket]=($areaCounts[$c][$bucket]??0)+$n;
      foreach($ls as $l){
        $this->require(isset($public['breakdown'][$c][$bucket][$l]), 'Group positive missing from marginal records.');
        $positive[$c][$bucket][$l]=($positive[$c][$bucket][$l]??0)+$n;
      }
      // Explicit allowlist: no source identifiers or raw response fields survive.
      $groups[]=['county'=>$c,'municipality'=>$bucket,'languages'=>$ls,'count'=>$n];
    }
    foreach($public['breakdown'] as $c=>$areas)foreach($areas as $m=>$values)foreach($values as $l=>$n){
      $this->require(isset($areaCounts[$c][$m]), 'Marginal record has no submission group.');
      $this->require(($positive[$c][$m][$l]??0)===$n, 'Combination counts disagree with marginal records.');
    }
    $public['schemaVersion']=3;$public['combinations']=$groups;
    return $public;
  }

  private function normalizeDaily(array $input,array $counties,array $refs):array {
    $this->require(count($input)===6 && !array_diff(array_keys($input),['schema_version','synthetic','records','groups','daily_records','daily_groups']),'Unexpected daily fields.');
    $all=$this->normalize(['schema_version'=>3,'synthetic'=>$input['synthetic'],'records'=>$input['records'],'groups'=>$input['groups']],$counties,$refs);
    $byDay=[];$sums=[];
    foreach(['daily_records'=>'records','daily_groups'=>'groups'] as $source=>$target){$this->require(is_array($input[$source]) && array_is_list($input[$source]),'Daily rows required.');foreach($input[$source] as $r){$day=$r['day']??NULL;$this->require(array_key_exists('day',$r),'Missing day.');if($day!==NULL){$d=is_string($day)?\DateTimeImmutable::createFromFormat('!Y-m-d',$day):FALSE;$this->require($d && $d->format('Y-m-d')===$day,'Invalid local date.');}unset($r['day']);$byDay[$day??'_undated'][$target][]=$r;$key=$target.'/'.$r['county_geoid'].'/'.($r['municipality']??'_unspecified').'/'.($target==='records'?$r['language']:implode(',',$r['languages']));$sums[$key]=($sums[$key]??0)+$r['response_count'];}}
    $daily=[];foreach($byDay as $day=>$parts){$dto=$this->normalize(['schema_version'=>3,'synthetic'=>TRUE,'records'=>$parts['records']??[],'groups'=>$parts['groups']??[]],$counties,$refs);$daily[]=['day'=>$day==='_undated'?NULL:$day,'totals'=>$dto['totals'],'breakdown'=>$dto['breakdown'],'combinations'=>$dto['combinations']];}
    $expected=[];foreach(['records','groups'] as $target)foreach($input[$target] as $r){$key=$target.'/'.$r['county_geoid'].'/'.($r['municipality']??'_unspecified').'/'.($target==='records'?$r['language']:implode(',',$r['languages']));$expected[$key]=$r['response_count'];}ksort($expected);ksort($sums);$this->require($expected===$sums,'Daily counts disagree with all-time groups.');
    $all['schemaVersion']=4;$all['days']=$daily;$all['timezone']='America/New_York';return $all;
  }

}
