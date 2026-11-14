<?php
/** Read-only inspection of the local prototype after browser tests. */
$db=\Drupal::database();$checks=0;
$assert=function($ok,$message)use(&$checks){if(!$ok)throw new RuntimeException($message);$checks++;};
foreach(['lit_contribution_review','lit_contribution_audit','lit_contribution_publication'] as $table){
 $bytes=json_encode($db->select($table,'t')->fields('t')->execute()->fetchAll());
 $assert(!preg_match('/person-\d+@example|shared@example|"uses_language"|"first_languages"/i',$bytes),'Raw source content found in metadata table');
}
$cache=$db->select('key_value_expire','k')->fields('k',['value'])->condition('collection',['form','form_state'],'IN')->execute();
foreach($cache as $row)$assert(!preg_match('/person-\d+@example|shared@example|uses_language|first_languages/i',$row->value),'Raw private answers persisted in form cache');
$config=\Drupal::config('lit_contribution.settings');
$assert($config->get('invitation_enabled')===FALSE && $config->get('survey_url')==='','Invitation not restored to initial off/blank state');
$public=\Drupal::service('lit_contribution.publication')->publicData();
$assert($public!==NULL && !preg_match('/@example|R_DEMO|reviewer|submitted_at|email_digest/',json_encode($public)),'Public data privacy failure');
$summary=$db->select('lit_contribution_review','r')->fields('r',['status'])->countQuery()->execute()->fetchField();
echo "$checks privacy checks passed across metadata, current form-cache entries, invitation configuration and public payload.\n";
echo "Review references: $summary; public updated date: ".$public['date']."\n";
echo 'Bergen synthetic counts: '.json_encode($public['survey']['totals']['34003'])."\n";

$q=$db->select('lit_contribution_review','r')->fields('r',['status']);$q->addExpression('COUNT(*)','count');$q->groupBy('status');
echo 'Review status counts: '.json_encode($q->execute()->fetchAll())."\n";
