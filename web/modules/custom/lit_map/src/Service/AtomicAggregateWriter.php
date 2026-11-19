<?php
namespace Drupal\lit_map\Service;

/** Immutable aggregate files plus atomic rename; pointer switch is transactional. */
class AtomicAggregateWriter {
  public function __construct(private readonly string $appRoot) {}
  public function directory(): string { return dirname($this->appRoot).'/private/lit-map'; }
  public function write(array $document): string {
    $bytes=json_encode($document,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
    $hash=hash('sha256',$bytes); $dir=$this->directory();
    if (!is_dir($dir) && !@mkdir($dir,0700,TRUE) && !is_dir($dir)) throw new \RuntimeException('Cannot prepare aggregate storage.');
    $temp=$dir.'/.dataset-'.bin2hex(random_bytes(12));$handle=@fopen($temp,'xb');
    if (!$handle) throw new \RuntimeException('Cannot stage aggregate publication.');
    try {
      @chmod($temp,0600);$written=0;
      while ($written<strlen($bytes)) { $n=fwrite($handle,substr($bytes,$written));if (!$n) throw new \RuntimeException('Incomplete aggregate write.');$written+=$n; }
      if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) throw new \RuntimeException('Could not flush aggregate.');
      fclose($handle);$handle=NULL;
      if (!@rename($temp,$dir.'/'.$hash.'.json')) throw new \RuntimeException('Could not finalize aggregate.');
      return $hash;
    } finally { if (is_resource($handle)) fclose($handle);if (is_file($temp)) unlink($temp); }
  }
  public function read(string $hash): array {
    if (!preg_match('/^[a-f0-9]{64}$/D',$hash)) throw new \UnexpectedValueException('Invalid publication reference.');
    $bytes=@file_get_contents($this->directory().'/'.$hash.'.json');
    if ($bytes===FALSE || !hash_equals($hash,hash('sha256',$bytes))) throw new \RuntimeException('Published aggregate unavailable.');
    return json_decode($bytes,TRUE,512,JSON_THROW_ON_ERROR);
  }
}
