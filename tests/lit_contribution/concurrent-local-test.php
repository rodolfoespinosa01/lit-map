<?php
/** Shared isolated SQLite/file storage for two independent local submit processes. */
use Drupal\Core\Database\Database;
use Drupal\lit_contribution\Service\{ExportStore,ExportImporter,ReviewManager};
use Drupal\lit_contribution\Source\PublishedBaselineAndExports;
$dir=(string)getenv('LIT_QA_DIRECTORY');$mode=(string)getenv('LIT_QA_MODE');if(!preg_match('#^/tmp/lit-local-concurrency-[a-f0-9]+$#D',$dir))throw new RuntimeException('Choose an isolated QA directory.');
if($mode==='setup' && !is_dir($dir))mkdir($dir.'/web',0700,TRUE);
\Drupal::service('class_loader')->addPsr4('Drupal\\sqlite\\',DRUPAL_ROOT.'/core/modules/sqlite/src');Database::addConnectionInfo('lit_parallel','default',['driver'=>'sqlite','database'=>$dir.'/test.sqlite','prefix'=>'','namespace'=>'Drupal\\sqlite\\Driver\\Database\\sqlite']);$db=Database::getConnection('default','lit_parallel');
if($mode==='setup'){\Drupal::moduleHandler()->loadInclude('lit_contribution','install');foreach(lit_contribution_schema() as $t=>$d)$db->schema()->createTable($t,$d);chmod($dir.'/test.sqlite',0600);echo "Isolated concurrent test prepared.\n";return;}
$store=new ExportStore($dir.'/web',$db);
if($mode==='report'){$counts=['source'=>count($store->snapshot()),'reviews'=>(int)$db->select('lit_contribution_review','r')->countQuery()->execute()->fetchField(),'reservations'=>(int)$db->select('lit_contribution_email_reservation','e')->countQuery()->execute()->fetchField()];if($counts!==['source'=>1,'reviews'=>1,'reservations'=>1])throw new RuntimeException('Concurrent acceptance invariant failed.');echo "Two-process local submission: exactly one source record, pending review and email reservation.\n";return;}
$v=\Drupal::service('lit_contribution.validator');$source=new PublishedBaselineAndExports(\Drupal::service('lit_contribution.mock_source'),$store,$v);$account=\Drupal::currentUser();$account->setAccount(new \Drupal\Core\Session\AnonymousUserSession());$review=new ReviewManager($db,$source,$v,$account,\Drupal::service('private_key'),\Drupal::lock());$importer=new ExportImporter($db,$store,$v,$review);$raw=json_decode(file_get_contents(dirname(DRUPAL_ROOT).'/fixtures/lit-export/questionnaire-v2.json'),TRUE)['submissions'][0];
try{$importer->local($raw['answers'],'concurrent-local@example.org');echo "Accepted one isolated demonstration.\n";}catch(RuntimeException $e){echo "Competing duplicate/busy demonstration refused safely.\n";}
