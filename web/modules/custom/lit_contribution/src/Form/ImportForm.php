<?php
namespace Drupal\lit_contribution\Form;
use Drupal\Core\Form\{FormBase,FormStateInterface};
use Drupal\Core\Url;
use Drupal\lit_contribution\Source\NormalizedExportMapper;

final class ImportForm extends FormBase {
  public function getFormId():string{return 'lit_contribution_import';}
  public function buildForm(array $form,FormStateInterface $form_state):array {
    \Drupal::service('lit_contribution.owner')->requireOwner();$form['#cache']['max-age']=0;
    $form['notice']=['#plain_text'=>'Import normalized CSV/JSON exports. Simulated export—not yet verified against Qualtrics. Preview first; confirmation accepts valid rows only and never approves or publishes. Maximum 2 MiB / 1,000 records. Conflicts and invalid rows are excluded.'];
    foreach(['csv','json'] as $format)$form['example_'.$format]=['#type'=>'link','#title'=>'Download questionnaire v2 synthetic '.strtoupper($format).' example','#url'=>Url::fromRoute('lit_contribution.import_example',['format'=>$format]),'#prefix'=>'<p>','#suffix'=>'</p>'];
    if($report=$form_state->get('preview')){
      $form['summary']=['#type'=>'table','#header'=>['Category','Count'],'#rows'=>[]];foreach($report['counts'] as $k=>$v)$form['summary']['#rows'][]=[ucfirst(str_replace('_',' ',$k)),$v];
      $form['explanation']=['#plain_text'=>'New/changed/already imported/duplicate/invalid are exclusive categories. Permission-ineligible overlaps accepted categories. Such responses reserve their email but do not appear in the eligible pending queue or publication. Missing records are preserved.'];
      $form['rows']=['#type'=>'table','#header'=>['Record ordinal','Result','Explanation'],'#rows'=>[]];foreach($report['rows'] as $r)$form['rows']['#rows'][]=[$r['row'],ucfirst(str_replace('_',' ',$r['result'])),['data'=>['#plain_text'=>$r['message']]]];
      $form['confirm']=['#type'=>'submit','#value'=>'Confirm valid records','#mode'=>'confirm'];
      $form['cancel']=['#type'=>'submit','#value'=>'Discard preview','#mode'=>'cancel','#limit_validation_errors'=>[],'#submit'=>['::submitForm']];
    }else{
      $form['export']=['#type'=>'file','#title'=>'Survey export','#description'=>'UTF-8 .csv or .json using the normalized format. No files or answers are placed in the public web root.','#attributes'=>['accept'=>'.csv,.json']];
      $form['preview']=['#type'=>'submit','#value'=>'Validate and preview','#mode'=>'preview'];
    }
    $form['history']=['#type'=>'details','#title'=>'Import history (latest 20)'];
    foreach(\Drupal::service('lit_contribution.importer')->history() as $h)$form['history']['h'.$h['id']]=['#type'=>'details','#title'=>'Import '.$h['id'].' · '.gmdate('Y-m-d H:i:s \U\T\C',$h['occurred_at']).' · UID '.$h['actor_uid'],'report'=>['#prefix'=>'<pre>','#suffix'=>'</pre>','#plain_text'=>json_encode(json_decode($h['summary'],TRUE),JSON_PRETTY_PRINT)]];
    return $form;
  }
  public function validateForm(array &$form,FormStateInterface $form_state):void {
    if(($form_state->getTriggeringElement()['#mode']??'')!=='preview')return;
    try{
      $files=$this->getRequest()->files->get('files',[]);$file=$files['export']??NULL;
      if(!$file || !$file->isValid() || $file->getSize()>NormalizedExportMapper::MAX_BYTES)throw new \InvalidArgumentException('Choose a valid CSV/JSON file up to 2 MiB.');
      $rows=\Drupal::service('lit_contribution.export_mapper')->decode(file_get_contents($file->getPathname()),strtolower($file->getClientOriginalExtension()));
      $report=\Drupal::service('lit_contribution.importer')->preview($rows);unset($report['accepted']);
      $token=\Drupal::service('lit_contribution.export_store')->stage($rows,(int)$this->currentUser()->id());
      $form_state->set('stage_token',$token)->set('preview',$report);
    }catch(\Throwable $e){$form_state->setErrorByName('export',$e instanceof \InvalidArgumentException?$e->getMessage():'Could not prepare a private preview. Check the format or retry.');}
  }
  public function submitForm(array &$form,FormStateInterface $form_state):void {
    $mode=$form_state->getTriggeringElement()['#mode'];if($mode==='preview'){$form_state->setRebuild();return;}
    $store=\Drupal::service('lit_contribution.export_store');$token=(string)$form_state->get('stage_token');
    try{
      if($mode==='confirm'){$counts=\Drupal::service('lit_contribution.importer')->confirm($store->staged($token,(int)$this->currentUser()->id()),$form_state->get('preview')['signature']);$this->messenger()->addStatus('Import completed. New: '.$counts['new'].', changed: '.$counts['changed'].', already imported: '.$counts['already_imported'].', duplicate-email excluded: '.$counts['duplicate_email'].', invalid: '.$counts['invalid'].', permission-ineligible: '.$counts['permission_ineligible'].'. Review and publication remain separate.');}
      $store->discard($token);$form_state->setRedirect('lit_contribution.import');
    }catch(\Throwable $e){$this->getLogger('lit_contribution')->error('Import failed: @type',['@type'=>get_class($e)]);$this->messenger()->addError('Import not applied. Preview again: the source/review state may have changed, the preview expired, or private storage is unavailable.');$store->discard($token);$form_state->setRedirect('lit_contribution.import');}
  }
}
