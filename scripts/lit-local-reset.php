<?php
/** Drush php:script entry point; arguments use environment to avoid Drush parsing. */
use Drupal\lit_contribution\Service\LocalReset;
ini_set('memory_limit','1024M');
try{
  $tool=new LocalReset();$tool->guard();$preset=getenv('LIT_RESET_PRESET')?:'';$restore=getenv('LIT_RESET_RESTORE')?:'';
  if($restore && $preset)throw new RuntimeException('Choose either preset or restore.');
  echo json_encode($restore?$tool->previewRestore($restore):$tool->preview($preset),JSON_PRETTY_PRINT).PHP_EOL;
  if(getenv('LIT_RESET_BACKUP')==='yes'){if($restore)throw new RuntimeException('Choose backup or restore.');echo 'Recovery backup: '.$tool->backupOnly($preset).PHP_EOL;return;}
  if(getenv('LIT_RESET_EXECUTE')!=='yes'){if($restore)echo "Restore preview: supplied backup will replace its scoped LiT data; current data is backed up first.\n";else echo "Preview only; no changes.\n";return;}
  if(!stream_isatty(STDIN))throw new RuntimeException('Execution requires an interactive terminal.');
  $phrase=$restore?'RESTORE '.$restore:'RESET '.$preset;
  echo "Type exactly: $phrase\n";if(trim(fgets(STDIN)?:'')!==$phrase)throw new RuntimeException('Confirmation did not match.');
  $backup=$restore?$tool->restore($restore):$tool->run($preset);
  echo "Completed. Recovery backup: $backup\n";
}catch(Throwable $e){fwrite(STDERR,'Local reset stopped. Check local opt-in, preset, confirmation and private/lit-reset-incomplete. Private error details suppressed.'.PHP_EOL);exit(1);}
