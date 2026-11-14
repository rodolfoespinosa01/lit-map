<?php
/** Explicit schema upgrade publication. Never synchronize or change review decisions. */
if(getenv('IS_DDEV_PROJECT')!=='true')throw new RuntimeException('Local DDEV only.');
$db=\Drupal::database();
$decisions=function()use($db){$r=$db->select('lit_contribution_review','r')->fields('r')->orderBy('id')->execute()->fetchAll(PDO::FETCH_ASSOC);foreach($r as &$v)unset($v['published_at'],$v['in_publication']);unset($v);return hash('sha256',json_encode([$r,$db->select('lit_contribution_audit','a')->fields('a')->orderBy('id')->execute()->fetchAll(PDO::FETCH_ASSOC)]));};
$before=$decisions();$account=\Drupal::service('account_switcher');$account->switchTo(\Drupal\user\Entity\User::load(\Drupal::service('lit_contribution.owner')->ownerId()));
try{echo json_encode(\Drupal::service('lit_contribution.publication')->publish()).PHP_EOL;}finally{$account->switchBack();}
if($decisions()!==$before)throw new RuntimeException('Review decision/audit preservation failed');
echo "Review records and audit preserved; only publication membership/timestamps updated. Decision fingerprint: $before\n";
