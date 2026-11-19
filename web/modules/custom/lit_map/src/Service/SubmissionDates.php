<?php
namespace Drupal\lit_map\Service;
final class SubmissionDates {
  public const ZONE='America/New_York';
  public static function day(?string $timestamp):?string {return $timestamp?(new \DateTimeImmutable($timestamp))->setTimezone(new \DateTimeZone(self::ZONE))->format('Y-m-d'):NULL;}
  public static function bounds(string $from='',string $through=''):array {
    $dates=[];foreach([$from,$through] as $value){if($value===''){$dates[]=NULL;continue;}$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone(self::ZONE));if(!$d || $d->format('Y-m-d')!==$value)throw new \InvalidArgumentException('Use valid calendar dates.');$dates[]=$d;}
    if($dates[0] && $dates[1] && $dates[0]>$dates[1])throw new \InvalidArgumentException('From must not be after Through.');
    return ['from'=>$dates[0]?->setTimezone(new \DateTimeZone('UTC')),'until'=>$dates[1]?->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))];
  }
  public static function includes(?string $day,string $from='',string $through=''):bool {self::bounds($from,$through);return $day===NULL?($from==='' && $through===''):(($from==='' || $day>=$from) && ($through==='' || $day<=$through));}
}
