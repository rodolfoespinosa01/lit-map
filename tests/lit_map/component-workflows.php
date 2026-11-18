<?php
/** Persisted reviewer inside a rollback transaction; isolated service databases. */
if(!in_array(getenv('DDEV_PROJECT'),['lit-component-d10','lit-component-d11'],TRUE))throw new RuntimeException('Disposable host only.');
$actorTransaction=\Drupal::database()->startTransaction();
try{
  $actor=\Drupal\user\Entity\User::create(['name'=>'component-workflow-'.bin2hex(random_bytes(4)),'mail'=>'component-workflow@example.org','status'=>1,'roles'=>['lit_contribution_administrator']]);$actor->save();putenv('LIT_QA_REVIEWER_UID='.$actor->id());
  // Separate function scopes avoid collisions between legacy test variables.
  (function(){require dirname(__DIR__).'/lit_contribution/scoped-workflow-test.php';})();
  (function(){require dirname(__DIR__).'/lit_contribution/export-import-test.php';})();
}finally{putenv('LIT_QA_REVIEWER_UID');$actorTransaction->rollBack();}
echo "Transactional reviewer rolled back; no existing account changed.\n";
