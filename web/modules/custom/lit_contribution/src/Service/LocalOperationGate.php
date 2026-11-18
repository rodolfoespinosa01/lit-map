<?php
namespace Drupal\lit_contribution\Service;

/** OS locks do not expire during long backups; shared by web requests and CLI mutations. */
final class LocalOperationGate {
  public static function acquire(bool $exclusive = FALSE) {
    $root=dirname(DRUPAL_ROOT).'/private';
    if(!file_exists($root) && !is_link($root) && realpath(dirname($root))===dirname($root))mkdir($root,0700);
    if(is_link($root) || !is_dir($root) || realpath($root)!==$root)throw new \RuntimeException('Unsafe private storage.');
    $path=$root.'/lit-operation.lock';
    if(is_link($path))throw new \RuntimeException('Unsafe operation lock.');
    $h=fopen($path,'c');if(!$h)throw new \RuntimeException('Operation gate unavailable.');chmod($path,0600);
    if(!flock($h,($exclusive?LOCK_EX:LOCK_SH)|LOCK_NB)){fclose($h);throw new \RuntimeException('Another local operation is running.');}
    if(!$exclusive && is_file($root.'/lit-reset-incomplete')){fclose($h);throw new \RuntimeException('Local recovery required.');}
    return $h;
  }
}
