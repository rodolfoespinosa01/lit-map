<?php
namespace Drupal\lit_map\Service;

/** Sourced place names, never additional boundaries or metric records. */
final class CommunityPlaces {
  public static function fold(string $s):string {return mb_strtolower(trim(preg_replace('/\s+/u',' ',$s)));}
  public static function load(array $towns):array {
    $data=json_decode(file_get_contents(__DIR__.'/../../data/community-places.json'),TRUE,32,JSON_THROW_ON_ERROR);
    if(($data['version']??NULL)!==1 || !is_array($data['communities']??NULL))throw new \UnexpectedValueException('Invalid community catalogue.');
    $index=array_column($towns,NULL,'id');$out=[];
    foreach($data['communities'] as $r){
      if(!is_string($r['name']??NULL) || trim($r['name'])==='' || ($r['state']??NULL)!=='NJ' || !isset($index[$r['municipality']??'']) || $index[$r['municipality']]['county']!==($r['county']??NULL) || !str_starts_with($r['source']??'','https://'))throw new \UnexpectedValueException('Invalid community reference.');
      $out[]=array_intersect_key($r,array_flip(['name','state','county','municipality','source']));
    }return $out;
  }
  /** Return all scoped candidates; callers must require exactly one. */
  public static function candidates(string $name,string $county,string $state,array $towns,?array $communities=NULL):array {
    if(!in_array(self::fold($state),['nj','new jersey'],TRUE))return [];
    $name=self::fold($name);$ids=[];
    foreach($towns as $t)if($t['county']===$county && in_array($name,[self::fold($t['name']),self::fold(preg_replace('/ (city|town|township|borough|village)$/i','',$t['name']))],TRUE))$ids[]=$t['id'];
    foreach($communities??self::load($towns) as $r)if($r['county']===$county && $r['state']==='NJ' && self::fold($r['name'])===$name)$ids[]=$r['municipality'];
    return array_values(array_unique($ids));
  }
}
