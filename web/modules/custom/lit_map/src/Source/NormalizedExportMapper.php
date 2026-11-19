<?php
namespace Drupal\lit_map\Source;

/** Versioned normalized interchange; deliberately not a Qualtrics column map. */
final class NormalizedExportMapper {
  public const MAX_BYTES = 52428800;
  public const MAX_RECORDS = 50000;
  public const COLUMNS = ['schema_version','survey_id','response_id','submitted_at','updated_at','answers'];
  public function decode(string $bytes, string $format): array {
    if (strlen($bytes)>self::MAX_BYTES || str_contains($bytes,"\0") || !mb_check_encoding($bytes,'UTF-8')) throw new \InvalidArgumentException('Use UTF-8 text up to 50 MiB, without NUL bytes.');
    $bytes=preg_replace('/^\xEF\xBB\xBF/','',$bytes);
    if ($format==='json') {
      try {$d=json_decode($bytes,TRUE,32,JSON_THROW_ON_ERROR);} catch(\Throwable $e){throw new \InvalidArgumentException('Invalid JSON document.');}
      if(!is_array($d) || count($d)!==2 || !in_array($d['export_version']??NULL,[1,2],TRUE) || !is_array($d['submissions']??NULL) || !array_is_list($d['submissions'])) throw new \InvalidArgumentException('Expected export_version 1 or 2 and a submissions array only.');
      $rows=$d['submissions'];
    } elseif($format==='csv') {
      $f=fopen('php://temp','w+');fwrite($f,$bytes);rewind($f);
      try {
        $header=fgetcsv($f,0,',','"','');
        if($header!==self::COLUMNS)throw new \InvalidArgumentException('CSV columns must exactly match the documented normalized format.');
        $rows=[];
        while(($cells=fgetcsv($f,0,',','"',''))!==FALSE){
          if($cells===[NULL])continue;
          $r=count($cells)===count($header)?array_combine($header,$cells):NULL;
          if($r){foreach(['submitted_at','updated_at'] as $key)if($r[$key]==='')$r[$key]=NULL;$r['schema_version']=in_array($r['schema_version'],['1','2'],TRUE)?(int)$r['schema_version']:$r['schema_version'];try{$r['answers']=json_decode($r['answers'],TRUE,16,JSON_THROW_ON_ERROR);}catch(\Throwable $e){$r['answers']=NULL;}}
          $rows[]=$r;if(count($rows)>self::MAX_RECORDS)break;
        }
      }finally{fclose($f);}
    }else throw new \InvalidArgumentException('Choose CSV or JSON.');
    if(!$rows || count($rows)>self::MAX_RECORDS)throw new \InvalidArgumentException('Supply between 1 and 50,000 records.');
    return $rows;
  }
}
