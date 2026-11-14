<?php
namespace Drupal\lit_contribution\Controller;
use Drupal\lit_contribution\Source\PublishedBaselineAndExports;
final class DatasetController {
  public function build():array {
    \Drupal::service('lit_contribution.owner')->requireOwner();
    $groups=[];foreach(\Drupal::database()->select('lit_contribution_review','r')->fields('r',['survey_id','response_id','status','origin'])->execute() as $r){$kind=$r->origin==='upload'?'Operational upload':(PublishedBaselineAndExports::baseline($r->survey_id,$r->response_id)?'Included baseline':'Excluded fixture/archive');$k=$r->survey_id.' / '.$kind.' / '.$r->status;$groups[$k]=($groups[$k]??0)+1;}
    ksort($groups);$rows=[];foreach($groups as $k=>$count)$rows[]=[$k,$count];
    return ['#cache'=>['max-age'=>0],'notice'=>['#plain_text'=>'Read-only dataset separation. The exact seeded pending references SYN_LIT_10000_V1 / R_LARGE_10001 through R_LARGE_10100 remain preserved, but are excluded from the operational queue and publication. The selected 10,000-response baseline is shown below; the other baseline remains archived and is never added to it. Older and unknown non-upload references remain untouched and excluded. Imports cannot alter reserved SYN_LIT_ survey IDs.'],'table'=>['#type'=>'table','#header'=>['Survey / composition / review status','Records'],'#rows'=>$rows]];
  }
}
