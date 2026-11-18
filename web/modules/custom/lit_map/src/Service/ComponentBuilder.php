<?php
namespace Drupal\lit_map\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\paragraphs\ParagraphInterface;

/** Shared renderer for Paragraphs and development preview routes. */
final class ComponentBuilder {

  /** Fixed trusted SVG, namespaced in PHP; labels come from editorial terms. */
  private function svg(string $id): \Drupal\Core\Render\Markup {
    $path=\Drupal::service('extension.list.module')->getPath('lit_map').'/templates/geometry/nj-counties.svg';
    $xml=new \DOMDocument();$xml->load($path,LIBXML_NONET);$xpath=new \DOMXPath($xml);
    $names=array_column(\Drupal::service('lit_map.references')->counties()['counties'],'name','geoid');
    foreach($xpath->query('//*[@id]') as $node)$node->setAttribute('id',$id.'-'.$node->getAttribute('id'));
    foreach($xpath->query('//*[@data-geoid]') as $node){$name=$names[$node->getAttribute('data-geoid')]??'';$node->setAttribute('data-county-name',$name);foreach($node->childNodes as $child)if($child->localName==='title')$child->textContent=$name.' County';}
    return \Drupal\Core\Render\Markup::create($xml->saveXML($xml->documentElement));
  }
  public function geometry(): array {
    return ['#theme'=>'lit_map_geometry_preview','#counties'=>\Drupal::service('lit_map.references')->counties()['counties'],'#attached'=>['library'=>['lit_map/geometry_preview']],'#cache'=>['tags'=>['taxonomy_term_list:lit_counties'],'contexts'=>['languages:language_content'],'max-age'=>300]];
  }
  public function interaction(): array {
    $data=\Drupal::service('lit_map.survey_data')->load();
    $build=['#theme'=>'lit_map_interaction_preview','#available'=>$data!==NULL,'#attached'=>['library'=>['lit_map/interactions']],'#cache'=>['max-age'=>0]];
    if($data!==NULL)$build['#attached']['drupalSettings']['litMapPreview']=$data;
    return $build;
  }
  public function build(string $source='informational', ?ParagraphInterface $paragraph=NULL): array {
    if (!in_array($source,['informational','published'],TRUE)) throw new \InvalidArgumentException('Unknown LiT source.');
    $cache=(new CacheableMetadata())->setCacheTags(['taxonomy_term_list:lit_counties','taxonomy_term_list:lit_languages','node_list:lit_resource','lit_map:publication','config:media.settings'])->setCacheContexts(['user.permissions','user.node_grants:view','languages:language_content'])->setCacheMaxAge(300);
    $data=NULL;$public=NULL;$date=NULL;$invitation=NULL;
    try {
      if($source==='published' && \Drupal::hasService('lit_contribution.publication')) {
        $public=\Drupal::service('lit_contribution.publication')->publicData();
        if($public && !in_array($public['survey']['schemaVersion'],[3,4],TRUE))throw new \RuntimeException('A combination aggregate is required for published union counts.');
        if($public){$data=\Drupal::service('lit_map.bubble_data')->load($public['survey']);$date=$public['date'];}
        $invitation=\Drupal::service('lit_contribution.invitation')->url();
        $cache->addCacheTags(['config:lit_contribution.settings']);
      }
      elseif($source==='informational') $data=\Drupal::service('lit_map.bubble_data')->load();
    } catch(\Throwable $e) { \Drupal::logger('lit_map')->error('Component data unavailable: @type',['@type'=>get_class($e)]); }
    // Development fixtures are intentionally read on every request.
    if($source==='informational')$cache->setCacheMaxAge(0);
    $id=Html::getUniqueId('lit-map-'.($paragraph?$paragraph->uuid():$source));
    $build=['#theme'=>'lit_map_bubble_preview','#instance_id'=>$id,'#svg'=>$this->svg($id), '#available'=>$data!==NULL,'#approved_preview'=>$source==='published','#publication_date'=>$date,'#empty_publication'=>$public!==NULL && empty($public['survey']['combinations']),'#invitation_url'=>$invitation,'#attached'=>['library'=>['lit_map/bubbles']]];
    $references=\Drupal::service('lit_map.references');
    $allowed=[];$anonymous=new AnonymousUserSession();
    foreach(['counties'=>'lit_counties','languages'=>'lit_languages'] as $kind=>$vid){
      $terms=$paragraph && !$paragraph->get('field_lit_'.$kind)->isEmpty()?$paragraph->get('field_lit_'.$kind)->referencedEntities():$references->terms($vid);
      foreach($terms as $term){
        $term=\Drupal::service('entity.repository')->getTranslationFromContext($term);
        $cache->addCacheableDependency($term);$access=$term->access('view',$anonymous,TRUE);$cache->addCacheableDependency($access);
        if($term->bundle()!==$vid || !$access->isAllowed() || !$term->isPublished())continue;
        $key=$term->get($kind==='counties'?'field_lit_geoid':'field_lit_language_key')->value;
        $allowed[$kind][$key]=$term->label();
      }
    }
    if($paragraph){
      $cache->addCacheableDependency($paragraph);
      $build['#heading']=$paragraph->field_lit_heading->value;
      if(!$paragraph->field_lit_intro->isEmpty())$build['#intro']=$paragraph->field_lit_intro->view(['label'=>'hidden']);
    }
    if($data){
      $survey=&$data['survey'];
      $survey['counties']=array_values(array_filter($survey['counties'],fn($c)=>isset($allowed['counties'][$c['geoid']])));
      foreach($survey['counties'] as &$c)$c['name']=$allowed['counties'][$c['geoid']];unset($c);
      $survey['languages']=array_values(array_filter($survey['languages'],fn($l)=>isset($allowed['languages'][$l['id']])));
      foreach($survey['languages'] as &$l)$l['label']=$allowed['languages'][$l['id']];unset($l);
      $survey['municipalities']=array_values(array_filter($survey['municipalities'],fn($m)=>isset($allowed['counties'][$m['county']])));
      $filter=function(array &$slice)use($allowed):void{
        foreach(['totals','breakdown'] as $key)$slice[$key]=array_intersect_key($slice[$key]??[],$allowed['counties']??[]);
        foreach($slice['totals'] as &$values)$values=array_intersect_key($values,$allowed['languages']??[]);unset($values);
        foreach($slice['breakdown'] as &$areas)foreach($areas as &$values)$values=array_intersect_key($values,$allowed['languages']??[]);unset($areas,$values);
        if(isset($slice['combinations'])) {
          $slice['combinations']=array_values(array_filter($slice['combinations'],fn($g)=>isset($allowed['counties'][$g['county']])));
          foreach($slice['combinations'] as &$g)$g['languages']=array_values(array_intersect($g['languages'],array_keys($allowed['languages']??[])));unset($g);
        }
      };
      $filter($survey);if(isset($survey['days']))foreach($survey['days'] as &$day)$filter($day);unset($day);
      $cards=\Drupal::service('lit_map.resources')->cards($paragraph?$paragraph->field_lit_resources->referencedEntities():NULL,$cache);
      $cards=array_values(array_filter($cards,fn($r)=>(!$r['county'] || isset($allowed['counties'][$r['county']])) && (bool)array_intersect($r['languages'],array_keys($allowed['languages']??[]))));
      $build['#resources']=$cards;
      $data['communities']=array_values(array_filter($data['communities']??[],fn($c)=>isset($allowed['counties'][$c['county']])));
      $settings=$data+['openingPreset'=>$source==='published','emptyPublication'=>$build['#empty_publication'],'allowedCounties'=>array_keys($allowed['counties']??[]),'publicationHash'=>$public['hash']??NULL,'today'=>(new \DateTimeImmutable('now',new \DateTimeZone('America/New_York')))->format('Y-m-d'),'resources'=>array_map(fn($r)=>array_diff_key($r,['card'=>TRUE]),$cards)];
      if($source==='published' && \Drupal::hasService('lit_contribution.publication'))$settings['panelsUrl']=\Drupal\Core\Url::fromRoute('lit_contribution.panels')->toString();
      if($paragraph){
        $settings['stateKey']='p'.$paragraph->uuid();
        $settings['municipalitiesEnabled']=(bool)$paragraph->field_lit_municipalities->value;
        $settings['defaultBreakdown']=(bool)$paragraph->field_lit_breakdown->value;
        $settings['defaultLanguages']=array_values(array_filter(array_map(fn($t)=>$t->field_lit_language_key->value,$paragraph->field_lit_defaults->referencedEntities()),fn($key)=>isset($allowed['languages'][$key])));
      }
      $build['#attached']['drupalSettings']['litMapBubbles'][$id]=$settings;
      $rows=[];foreach($survey['counties'] as $county)foreach($survey['languages'] as $language){$n=$survey['totals'][$county['geoid']][$language['id']]??NULL;$rows[]=[$county['name'],$language['label'],$n===NULL?'No data':(string)$n];}
      $build['#fallback']=['#type'=>'table','#caption'=>'All-time survey responses by county and language (language counts overlap)','#header'=>['County','Language','Responses'],'#rows'=>$rows];
    }
    $cache->applyTo($build);
    return $build;
  }
}
