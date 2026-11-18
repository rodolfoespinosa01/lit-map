<?php
/** Read-only fingerprints; never prints credentials or private review records. */
$db=\Drupal::database();$result=[];
foreach($db->schema()->findTables('lit_contribution_%') as $table){
  $rows=$db->select($table,'t')->fields('t')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  $encoded=array_map(function($r){ksort($r);return json_encode($r,JSON_THROW_ON_ERROR);},$rows);sort($encoded,SORT_STRING);
  $result[$table]=['rows'=>count($rows),'sha256'=>hash('sha256',implode("\n",$encoded))];
}
$users=$db->select('users_field_data','u')->fields('u',['uid','name','mail','pass','status'])->orderBy('uid')->execute()->fetchAll(\PDO::FETCH_ASSOC);
$result['account_credentials']=['rows'=>count($users),'sha256'=>hash('sha256',json_encode($users,JSON_THROW_ON_ERROR))];
ksort($result);echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
