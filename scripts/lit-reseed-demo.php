<?php
/** Explicit opt-in dataset replacement, no accounts or review records. */
$path=\Drupal::service('extension.list.module')->getPath('lit_map').'/data/demo-10000.v1.json';
\Drupal::service('lit_map.dataset')->activate(json_decode(file_get_contents($path),TRUE,512,JSON_THROW_ON_ERROR));
echo "10,000-response synthetic snapshot activated.\n";
