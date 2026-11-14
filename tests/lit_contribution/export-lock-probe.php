<?php
/** Two-process test of the shared import/review/publication lock; no record writes. */
use Drupal\lit_contribution\Service\ReviewManager;
if(getenv('LIT_LOCK_TEST_HOLD')==='yes'){
 $l=\Drupal::lock();if(!$l->acquire(ReviewManager::LOCK,30))throw new RuntimeException('Lock already occupied.');
 try{echo "LOCK_READY\n";flush();sleep(15);}finally{$l->release(ReviewManager::LOCK);}
}else{
 try{\Drupal::service('lit_contribution.reviews')->locked(function(){throw new LogicException('No concurrent holder; rerun with holder.');});}
 catch(RuntimeException $e){if(!str_contains($e->getMessage(),'Another contribution operation'))throw $e;echo "Concurrent mutation rejected safely; no record writes.\n";}
}
