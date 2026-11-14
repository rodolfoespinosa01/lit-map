<?php
// Read-only: no synchronization, decisions, or publication.
$db=\Drupal::database();
$tables=[];
foreach(['review','audit','publication'] as $table) $tables[$table]=$db->select('lit_contribution_'.$table,'t')->fields('t')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
$source=\Drupal::service('lit_contribution.source')->listSubmissions();
$p=$tables['publication'][0]??NULL;
$doc=$p?\Drupal::service('lit_contribution.writer')->read($p['file_hash']):NULL;
$out=['metadata_sha256'=>hash('sha256',json_encode($tables)), 'source_sha256'=>hash('sha256',json_encode($source)), 'publication'=>$p,'document'=>$doc,'statuses'=>[],'duplicate_excluded'=>0,'permission_excluded'=>0,'eligible'=>[],'decisions_after_publication'=>[]];
foreach($tables['review'] as $r){
 $s=$source[$r['survey_id'].'/'.$r['response_id']]??NULL;
 $out['statuses'][$r['status']]=($out['statuses'][$r['status']]??0)+1;
 $duplicate=$r['duplicate_conflict']||($r['duplicate_choice']&&(int)$r['duplicate_choice']!==(int)$r['id']);
 $out['duplicate_excluded']+=(int)(bool)$duplicate;
 $out['permission_excluded']+=(int)($s&&$s['answers']['aggregate_permission']!=='yes');
 if($s&&$r['status']==='approved'&&!$duplicate&&$s['answers']['aggregate_permission']==='yes')$out['eligible'][]=['response'=>$r['response_id'],'county'=>$s['answers']['county_geoid'],'municipality'=>$s['answers']['municipality'],'en'=>$s['answers']['languages']['en']['uses_language']??NULL,'es'=>$s['answers']['languages']['es']['uses_language']??NULL,'reviewed_at'=>$r['reviewed_at'],'published'=>$r['in_publication'],'fingerprint_matches'=>hash_equals($r['fingerprint'],\Drupal::service('lit_contribution.validator')->fingerprint($s))];
}
foreach($tables['audit'] as $a)if($p&&$a['occurred_at']>$p['published_at'])$out['decisions_after_publication'][]=['action'=>$a['action'],'time'=>$a['occurred_at']];
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
