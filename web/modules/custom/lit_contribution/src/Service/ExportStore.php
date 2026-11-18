<?php
namespace Drupal\lit_contribution\Service;
use Drupal\Core\Database\Connection;

/** Raw exports live outside web/; Drupal stores only an immutable file pointer. */
final class ExportStore extends AtomicAggregateWriter {
  public function __construct(private readonly string $root, private readonly Connection $db){parent::__construct($root);}
  public function directory(): string {return dirname($this->root).'/private/lit-imports';}
  public function pointer(): string {return (string)($this->db->select('lit_contribution_import_state','s')->fields('s',['file_hash'])->condition('id',1)->execute()->fetchField()?:'');}
  public function snapshot(): array {$h=$this->pointer();return $h?$this->read($h):[];}
  public function point(string $hash,string $expected): void {
    $current=$this->db->select('lit_contribution_import_state','s')->fields('s',['file_hash'])->condition('id',1)->forUpdate()->execute()->fetchField();
    if(($current===FALSE?'':$current)!==$expected)throw new \RuntimeException('Source pointer changed concurrently. Preview again.');
    if($current===FALSE)$this->db->insert('lit_contribution_import_state')->fields(['id'=>1,'file_hash'=>$hash])->execute();
    else $this->db->update('lit_contribution_import_state')->fields(['file_hash'=>$hash])->condition('id',1)->execute();
  }
  public function stage(array $rows,int $uid): string {
    $gate=LocalOperationGate::acquire();
    $this->cleanup();$token=bin2hex(random_bytes(24));$dir=$this->directory().'/staging';
    if(!is_dir($dir) && !mkdir($dir,0700,TRUE) && !is_dir($dir))throw new \RuntimeException('Private staging unavailable.');
    $p=$dir.'/'.$token.'.json';$f=fopen($p,'xb');if(!$f)throw new \RuntimeException('Private staging unavailable.');
    try{chmod($p,0600);$b=json_encode(['uid'=>$uid,'expires'=>time()+3600,'rows'=>$rows],JSON_THROW_ON_ERROR);if(fwrite($f,$b)!==strlen($b))throw new \RuntimeException('Incomplete staging write.');}finally{fclose($f);}
    return $token;
  }
  public function staged(string $token,int $uid): array {
    if(!preg_match('/^[a-f0-9]{48}$/D',$token))throw new \RuntimeException('Preview expired. Upload again.');
    $p=$this->directory().'/staging/'.$token.'.json';$b=@file_get_contents($p);$d=$b?json_decode($b,TRUE):NULL;
    if(!$d || $d['uid']!==$uid || $d['expires']<time())throw new \RuntimeException('Preview expired. Upload again.');return $d['rows'];
  }
  public function discard(string $token):void {if(preg_match('/^[a-f0-9]{48}$/D',$token)){ $p=$this->directory().'/staging/'.$token.'.json';if(is_file($p))unlink($p);}}
  public function cleanup():int {
    $gate=LocalOperationGate::acquire();
    $n=0;foreach(glob($this->directory().'/staging/*.json')?:[] as $p)if(filemtime($p)<time()-3600){unlink($p);$n++;}return $n;
  }
}
