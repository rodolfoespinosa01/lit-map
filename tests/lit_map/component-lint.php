<?php
$root=dirname(__DIR__,2);$count=0;
foreach(['web/modules/custom/lit_map'] as $directory)foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory)) as $file){if($file->isFile() && in_array($file->getExtension(),['php','module','install'],TRUE)){exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()),$output,$code);if($code)throw new RuntimeException(implode("\n",$output));$count++;}}
echo "$count PHP files passed syntax checks.\n";

require_once $root.'/vendor/autoload.php';
$configs=glob($root.'/web/modules/custom/lit_map/config/install/*.yml');
foreach($configs as $file)\Symfony\Component\Yaml\Yaml::parseFile($file);
echo count($configs)." install configuration files parsed successfully.\n";
