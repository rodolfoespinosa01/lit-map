<?php
/** Enable only after an explicit development settings flag is loaded. */
if(getenv('IS_DDEV_PROJECT')!=='true' || !\Drupal::service('lit_contribution.invitation')->development())throw new RuntimeException('First enable the explicit lit_local_demo_enabled development settings flag.');
\Drupal::configFactory()->getEditable('lit_contribution.settings')->set('contribution_mode','local')->set('panel_threshold',5)->save();echo "Local demonstration mode enabled; external survey URL retained. No real raffle or live API connection.\n";
