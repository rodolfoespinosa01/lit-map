<?php
namespace Drupal\lit_contribution\Service;

/** Explicit CLI-only, allowlisted literal reader. Never sources/evaluates a file. */
final class BootstrapEnvironment {
  public const KEYS=['LIT_SUPERADMIN_EMAIL','LIT_SUPERADMIN_INITIAL_PASSWORD'];
  public static function parse(#[\SensitiveParameter] string $text): array {
    $values=[];
    foreach(preg_split('/\r?\n/',$text) as $line){
      $line=trim($line);if($line==='' || str_starts_with($line,'#'))continue;
      if(!preg_match('/^([A-Z_][A-Z0-9_]*)\s*=(.*)$/D',$line,$m))throw new \RuntimeException('Invalid local environment syntax; use literal KEY=value lines.');
      if(!in_array($m[1],self::KEYS,TRUE))continue;
      if(array_key_exists($m[1],$values))throw new \RuntimeException('Duplicate bootstrap variable.');
      $v=trim($m[2]);
      if(str_starts_with($v,'"') || str_starts_with($v,"'")){
        if(strlen($v)<2 || substr($v,-1)!==$v[0])throw new \RuntimeException('Unclosed environment quote.');
        $v=substr($v,1,-1);
      }
      $values[$m[1]]=$v; // $, backticks and # are literal; no interpolation or eval.
    }
    return $values;
  }
  public static function read(string $root): array {
    if(PHP_SAPI!=='cli')throw new \RuntimeException('Bootstrap environment is available only to explicit CLI commands.');
    $local=[];
    if(getenv('IS_DDEV_PROJECT')==='true' && is_file($root.'/.env')){
      if(filesize($root.'/.env')>16384)throw new \RuntimeException('Local environment file is too large.');
      $text=file_get_contents($root.'/.env');if($text===FALSE)throw new \RuntimeException('Cannot read local environment file.');
      $local=self::parse($text);
    }
    $values=[];foreach(self::KEYS as $key){$injected=getenv($key);$values[$key]=$injected!==FALSE?$injected:($local[$key]??'');}
    return $values;
  }
}
