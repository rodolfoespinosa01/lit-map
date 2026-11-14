<?php
use Drupal\lit_contribution\Service\BootstrapEnvironment;
try {
  echo \Drupal::service('lit_contribution.superadmin_bootstrap')->run(BootstrapEnvironment::read(dirname(DRUPAL_ROOT))).PHP_EOL;
} catch (\Throwable $e) { fwrite(STDERR,$e instanceof \InvalidArgumentException || $e instanceof \RuntimeException ? $e->getMessage().PHP_EOL : "Bootstrap failed; no credentials printed.\n");exit(1); }
