<?php
/** CLI file replacement, invoked by an authorized site operator through Drush. */
$path=getenv('LIT_DATASET_FILE');
if(!$path || !is_file($path))throw new RuntimeException('Set LIT_DATASET_FILE to a complete normalized CSV/JSON file.');
$bytes=file_get_contents($path);$format=strtolower(pathinfo($path,PATHINFO_EXTENSION));
\Drupal::service('lit_map.dataset')->import($bytes,$format,getenv('LIT_DATASET_SYNTHETIC')==='yes');
echo "Validated complete snapshot activated.\n";
