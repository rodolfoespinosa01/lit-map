<?php
/** Read-only local composition check. No raw answers or credentials printed. */
$s=\Drupal::service('account_switcher');$s->switchTo(\Drupal\user\Entity\User::load(1));
try {
 $db=\Drupal::database();
 $result=['operational_pending'=>count(\Drupal::service('lit_contribution.reviews')->listing(TRUE)),
  'uploaded_records'=>count(\Drupal::service('lit_contribution.export_store')->snapshot()),
  'publication'=>$db->select('lit_contribution_publication','p')->fields('p')->condition('id',1)->execute()->fetchAssoc(),
  'seeded_pending'=>$db->select('lit_contribution_review','r')->condition('survey_id','SYN_LIT_10000_V1')->condition('response_id',['R_LARGE_10001','R_LARGE_10100'],'BETWEEN')->condition('status','pending')->countQuery()->execute()->fetchField(),
  'baseline_approved'=>$db->select('lit_contribution_review','r')->condition('survey_id','SYN_LIT_10000_V1')->condition('response_id',['R_LARGE_00001','R_LARGE_10000'],'BETWEEN')->condition('status','approved')->countQuery()->execute()->fetchField(),
  'matches_publication'=>\Drupal::service('lit_contribution.publication')->reconcile()['matches']];
 echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
}finally{$s->switchBack();}
