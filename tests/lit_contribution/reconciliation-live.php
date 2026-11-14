<?php
// Read-only Drupal check. Does not synchronize, review or publish.
$account=\Drupal::service('account_switcher');$account->switchTo(\Drupal\user\Entity\User::load(1));
try{
 $r=\Drupal::service('lit_contribution.publication')->reconcile();
 if(!$r['matches'])throw new RuntimeException('Current publication differs from eligibility; inspect reconciliation.');
 $public=\Drupal::service('lit_contribution.publication')->publicData();
 foreach($r['totals'] as $key=>$expected){[$county,$language]=explode('/',$key);if($public['survey']['totals'][$county][$language]!==$expected)throw new RuntimeException('Public DTO differs from publication');}
 echo json_encode(['counts'=>$r['counts'],'totals'=>$r['totals'],'publication'=>$r['publication'],'matches'=>$r['matches'],'audit_after'=>$r['after'],'audit_same_second'=>$r['sameSecond']],JSON_PRETTY_PRINT)."\n";
}finally{$account->switchBack();}
