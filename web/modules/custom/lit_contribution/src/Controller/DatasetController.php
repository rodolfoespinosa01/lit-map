<?php
namespace Drupal\lit_contribution\Controller;
use Drupal\lit_contribution\Source\PublishedBaselineAndExports;
final class DatasetController {
  public function build():array {
    \Drupal::service('lit_contribution.owner')->requireOwner();
    $groups=[];foreach(\Drupal::database()->select('lit_contribution_review','r')->fields('r',['survey_id','response_id','status','origin'])->execute() as $r){$kind=$r->origin==='upload'?'Operational upload':(PublishedBaselineAndExports::baseline($r->survey_id,$r->response_id)?'Included baseline':'Excluded fixture/archive');$k=$r->survey_id.' / '.$kind.' / '.$r->status;$groups[$k]=($groups[$k]??0)+1;}
    ksort($groups);$rows=[];foreach($groups as $k=>$count)$rows[]=[$k,$count];
    return ['#cache'=>['max-age'=>0],'notice'=>['#plain_text'=>'Read-only dataset composition. Only the selected baseline, when present, and eligible operational uploads contribute to publication. Excluded fixture/archive rows shown below are not combined with it. An empty table means no survey records remain. Imports cannot alter reserved SYN_LIT_ survey IDs.'],'table'=>['#type'=>'table','#header'=>['Survey / composition / review status','Records'],'#rows'=>$rows]];
  }
}
