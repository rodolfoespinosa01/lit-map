<?php
namespace Drupal\lit_contribution\Service;

/** Transcription-aligned v2; raw text/contacts and canonical resolution stay private. */
final class Questionnaire {
  public static function catalog():array {return json_decode(file_get_contents(__DIR__.'/../../data/questionnaire.v2.json'),TRUE,32,JSON_THROW_ON_ERROR)['questions'];}
  public const SKILLS=['speaking','reading','writing'];
  public const SUPPORT=['work_school'=>'At work or school','healthcare'=>'In healthcare settings','government'=>'In government or public offices','community'=>'In your local community'];
  public static function blank():array {
    $q=[];foreach(self::catalog() as $f){$q[$f['key']]=match($f['type']){'languages'=>array_fill(0,3,['text'=>'','age'=>NULL]),'frequency'=>array_fill(0,3,NULL),'proficiency'=>array_fill_keys(self::SKILLS,NULL),'support'=>array_fill_keys(array_keys(self::SUPPORT),NULL),'gender'=>['choice'=>NULL,'text'=>''],'contact'=>['name'=>'','email'=>''],default=>NULL};}return $q;
  }
  private static function need(bool $ok,string $key):void {if(!$ok)throw new \UnexpectedValueException('Invalid questionnaire field '.$key.'.');}
  public static function normalize(array $s,SubmissionValidator $v):array {
    self::need(count($s)===7 && !array_diff(array_keys($s),['schema_version','survey_id','response_id','email','submitted_at','updated_at','answers']),'envelope');
    // Reuse the version-1 identity/date checks with a minimal, explicit adapter.
    $id=$s;$id['schema_version']=1;$id['answers']=['county_geoid'=>'34013','municipality'=>NULL,'first_languages'=>[],'aggregate_permission'=>'no','languages'=>['en'=>['uses_language'=>'no','speaking'=>'not_applicable','signing'=>'not_applicable','home_use'=>'prefer_not_to_answer','other_settings'=>[],'frequency'=>'prefer_not_to_answer','brokering'=>'prefer_not_to_answer']]];
    $id=$v->normalize($id,FALSE);foreach(['email','survey_id','response_id','submitted_at','updated_at'] as $key)$s[$key]=$id[$key];
    $a=$s['answers'];self::need(is_array($a) && !array_diff(array_keys($a),['consent','map_permission','questions','resolutions','county_geoid','municipality','languages','aggregate_permission','flags','slot_languages']),'answers');
    self::need(($a['consent']??NULL)===TRUE,'consent_18_plus');self::need(in_array($a['map_permission']??NULL,['yes','no','unknown'],TRUE),'application_map_permission');
    $q=$a['questions']??NULL;$blank=self::blank();self::need(is_array($q) && !array_diff(array_keys($q),array_keys($blank)),'questions');$q=array_replace($blank,$q);
    foreach(self::catalog() as $f){$k=$f['key'];$x=$q[$k];$opt=array_keys($f['options']??[]);$choice=fn($z)=>$z===NULL || (is_string($z) && in_array($z,$opt,TRUE));
      switch($f['type']){
        case 'choice':self::need($choice($x),$k);break;
        case 'multiple':self::need($x===NULL || (is_array($x) && array_is_list($x) && count($x)===count(array_unique($x,SORT_REGULAR)) && !array_diff($x,$opt)),$k);if(is_array($x)){sort($x);$q[$k]=$x?:NULL;}break;
        case 'age':case 'years':self::need($x===NULL || (is_int($x) && $x>=0 && $x<=120),$k);if($k==='q04' && $x!==NULL)self::need($x>=18,'age_18_plus');break;
        case 'slider':self::need($x===NULL || (is_int($x) && $x>=0 && $x<=100),$k);break;
        case 'gender':self::need(is_array($x) && count($x)===2 && !array_diff(array_keys($x),['choice','text']) && $choice($x['choice']) && is_string($x['text']) && mb_strlen($x['text'])<=200,$k);break;
        case 'contact':self::need(is_array($x) && count($x)===2 && !array_diff(array_keys($x),['name','email']) && is_string($x['name']) && mb_strlen($x['name'])<=200 && is_string($x['email']) && strlen($x['email'])<=254 && ($x['email']==='' || filter_var($x['email'],FILTER_VALIDATE_EMAIL)),$k);break;
        case 'languages':self::need(is_array($x) && array_is_list($x) && count($x)===3,$k);foreach($x as $slot)self::need(is_array($slot) && count($slot)===2 && !array_diff(array_keys($slot),['text','age']) && is_string($slot['text']) && mb_strlen($slot['text'])<=100 && ($slot['age']===NULL || (is_int($slot['age']) && $slot['age']>=0 && $slot['age']<=120)),$k);break;
        case 'frequency':self::need(is_array($x) && array_is_list($x) && count($x)===3,$k);foreach($x as $z)self::need($choice($z),$k);break;
        case 'proficiency':case 'support':$keys=$f['type']==='proficiency'?self::SKILLS:array_keys(self::SUPPORT);self::need(is_array($x) && count($x)===count($keys) && !array_diff(array_keys($x),$keys),$k);foreach($x as $z)self::need($choice($z),$k);ksort($q[$k]);break;
        default:self::need($x===NULL || (is_string($x) && mb_strlen($x)<=($f['type']==='reflection'?2000:254)),$k);if($f['type']==='email' && $x!==NULL && $x!=='')self::need(filter_var($x,FILTER_VALIDATE_EMAIL)!==FALSE,$k);
      }
    }
    $res=$a['resolutions']??[];self::need(is_array($res) && !array_diff(array_keys($res),['county','municipality','languages']),'resolutions');
    $flags=[];$counties=array_column($v->counties()['counties'],NULL,'geoid');$towns=array_column($v->references()['municipalities'],NULL,'id');
    $fold=fn($x)=>mb_strtolower(trim(preg_replace('/\s+/u',' ',(string)$x)));
    $county=NULL;$raw=preg_replace('/\s+county$/i','',$fold($q['q03']));foreach($counties as $c)if($fold($c['name'])===$raw || $c['key']==='nj-'.str_replace(' ','-',$raw))$county=$c['geoid'];
    if($q['q01']==='yes'){if($county && $county!=='34013')$flags[]='Newark/county conflict';$county='34013';}
    if(isset($res['county'])){self::need(isset($counties[$res['county']]),'resolved_county');$county=$res['county'];}
    $town=NULL;$city=$q['q01']==='yes'?'newark':$fold(explode(',',(string)$q['q02'])[0]);
    $placeParts=explode(',',(string)$q['q02']);if($q['q01']!=='yes' && count($placeParts)>1 && !in_array($fold(end($placeParts)),['nj','new jersey'],TRUE))$flags[]='Non-NJ residence';
    if($county){$matches=\Drupal\lit_map\Service\CommunityPlaces::candidates($city,$county,in_array('Non-NJ residence',$flags,TRUE)?'outside-nj':'NJ',array_values($towns));if(count($matches)===1)$town=$matches[0];elseif($city!=='')$flags[]='Unmatched or ambiguous municipality';}else $flags[]='Unmatched county';
    if(array_key_exists('municipality',$res)){self::need($res['municipality']===NULL || (isset($towns[$res['municipality']]) && $towns[$res['municipality']]['county']===$county),'resolved_municipality');$town=$res['municipality'];$flags=array_values(array_diff($flags,['Unmatched or ambiguous municipality']));}
    $aliases=\Drupal::service('lit_map.references')->aliases();
    // Chinese/Mandarin/Cantonese are not silently merged into the old provisional zh group.
    $langs=[];$slotLanguages=[NULL,NULL,NULL];$resolved=$res['languages']??[];self::need(is_array($resolved),'language_resolutions');
    foreach($q['q07'] as $i=>$slot){$text=$fold($slot['text']);$canonical=$aliases[$text]??NULL;if(array_key_exists($i,$resolved)){self::need(isset(\Drupal::service('lit_map.references')->resolvableLanguages()[$resolved[$i]]),'resolved_language');$canonical=$resolved[$i];}if($text==='')continue;if(!$canonical){$flags[]='Unmatched or ambiguous language slot '.($i+1);continue;}if(isset($langs[$canonical]))$flags[]='Duplicate canonical language slots';$langs[$canonical]=['uses_language'=>'yes'];$slotLanguages[$i]=$canonical;}
    if(!$langs)$flags[]='No resolved reported language';ksort($langs);sort($flags);ksort($q);
    $q['q05']=['choice'=>$q['q05']['choice'],'text'=>$q['q05']['text']];$q['q30']=['name'=>$q['q30']['name'],'email'=>$q['q30']['email']];foreach($q['q07'] as &$slot)$slot=['text'=>$slot['text'],'age'=>$slot['age']];unset($slot);if(isset($res['languages']))ksort($res['languages']);ksort($res);
    $s['answers']=['consent'=>TRUE,'map_permission'=>$a['map_permission'],'questions'=>$q,'resolutions'=>$res,'county_geoid'=>$county,'municipality'=>$town,'languages'=>$langs,'slot_languages'=>$slotLanguages,'aggregate_permission'=>!$flags && $a['map_permission']==='yes'?'yes':'no','flags'=>$flags];ksort($s);return $s;
  }
}
