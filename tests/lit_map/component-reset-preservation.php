<?php
/** Reset/restore regression only on explicitly named disposable DDEV hosts. */
if(!in_array(getenv('DDEV_PROJECT'),['lit-component-d10','lit-component-d11'],TRUE))throw new RuntimeException('Disposable host only.');
use Drupal\lit_contribution\Service\LocalReset;
$db=\Drupal::database();
$hash=function()use($db){$data=[];foreach(['taxonomy_term_field_data','taxonomy_term__field_lit_language_key','taxonomy_term__field_lit_geoid','paragraphs_item_field_data','node_field_data'] as $table){$data[$table]=$db->select($table,'t')->fields('t')->execute()->fetchAll(PDO::FETCH_ASSOC);}return hash('sha256',json_encode($data));};
$before=$hash();$settings=\Drupal\Core\Site\Settings::getAll();new \Drupal\Core\Site\Settings($settings+['lit_local_demo_enabled'=>TRUE,'lit_local_reset_enabled'=>TRUE]);
$reset=new LocalReset();$backup=NULL;
try{$backup=$reset->run('empty');if($hash()!==$before)throw new RuntimeException('Reset altered taxonomy or component content.');echo "PASS: disposable empty reset preserved taxonomy, Paragraphs, resources and demo content.\n";}
finally{if($backup)$reset->restore($backup);new \Drupal\Core\Site\Settings($settings);}
if($hash()!==$before)throw new RuntimeException('Restore altered canonical content.');echo "PASS: reset restore preserved canonical content; original disposable application state restored.\n";
