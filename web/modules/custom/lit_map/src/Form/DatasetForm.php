<?php
namespace Drupal\lit_map\Form;
use Drupal\Core\Form\{FormBase,FormStateInterface};
final class DatasetForm extends FormBase {
  public function getFormId():string{return 'lit_map_dataset';}
  public function buildForm(array $form,FormStateInterface $form_state):array {
    $form['#attributes']['enctype']='multipart/form-data';
    $form['notice']=['#markup'=>'<p>Supply a complete normalized CSV/JSON export (up to 50 MiB / 50,000 records). Validation precedes replacement; a failed file leaves the current map unchanged. Native Qualtrics columns require an explicit mapping. This form does not collect survey answers.</p>'];
    $form['dataset']=['#type'=>'file','#title'=>$this->t('Complete dataset file'),'#required'=>TRUE];
    $form['synthetic']=['#type'=>'checkbox','#title'=>$this->t('This file contains synthetic demonstration data')];
    $form['actions']['submit']=['#type'=>'submit','#value'=>$this->t('Validate and replace dataset')];return $form;
  }
  public function validateForm(array &$form,FormStateInterface $state):void {
    $files=$this->getRequest()->files->get('files',[]);$file=$files['dataset']??NULL;
    if(!$file || !$file->isValid() || $file->getSize()>52428800 || !in_array(strtolower($file->getClientOriginalExtension()),['json','csv'],TRUE))$state->setErrorByName('dataset',$this->t('Supply a valid CSV or JSON file up to 50 MiB.'));
  }
  public function submitForm(array &$form,FormStateInterface $state):void {
    $file=$this->getRequest()->files->get('files')['dataset'];
    try{\Drupal::service('lit_map.dataset')->import(file_get_contents($file->getPathname()),strtolower($file->getClientOriginalExtension()),(bool)$state->getValue('synthetic'));$this->messenger()->addStatus($this->t('Dataset validated and replaced.'));}
    catch(\Throwable $e){$this->messenger()->addError($this->t('Dataset rejected. Check the normalized format, references, dates and source IDs. The previous dataset remains active.'));}
  }
}
