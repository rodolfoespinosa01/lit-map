<?php
/** Container-local lint, with no shell-variable expansion through DDEV. */
$directory=__DIR__.'/../../web/modules/custom/lit_contribution';
$count=0;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS)) as $file){
  if(!in_array($file->getExtension(),['php','install','module'],TRUE))continue;
  passthru(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()),$status);
  if($status!==0)exit($status);
  $count++;
}
echo "$count contribution PHP files passed syntax checks.\n";
