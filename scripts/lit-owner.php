<?php
/** Compatibility accessor: configured identity only, never a tracked credential. */
$values=\Drupal\lit_contribution\Service\BootstrapEnvironment::read(dirname(__DIR__));
return ['uid'=>(int)\Drupal::state()->get('lit_contribution.owner_uid',0), 'email'=>$values['LIT_SUPERADMIN_EMAIL']];
