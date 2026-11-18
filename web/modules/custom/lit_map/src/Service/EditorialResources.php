<?php
namespace Drupal\lit_map\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;

/** Drupal-native editorial resources; never reads survey answers. */
final class EditorialResources {
  public static function safeUrl(?string $url): bool {
    if (!$url || strlen($url)>2048 || preg_match('/[\x00-\x20\x7f\\\\]/',$url) || !filter_var($url,FILTER_VALIDATE_URL)) return FALSE;
    $parts=parse_url($url);
    return ($parts['scheme']??'')==='https' && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
  }
  public function cards(?array $nodes, CacheableMetadata $cache): array {
    $storage=\Drupal::entityTypeManager()->getStorage('node');
    if ($nodes===NULL) $nodes=$storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->condition('type','lit_resource')->execute());
    $cards=[];$anonymous=new AnonymousUserSession();
    foreach($nodes as $node) {
      $node=\Drupal::service('entity.repository')->getTranslationFromContext($node);
      $cache->addCacheableDependency($node);
      if($node->bundle()!=='lit_resource')continue;
      $access=$node->access('view',$anonymous,TRUE);$cache->addCacheableDependency($access);
      if(!$node->isPublished() || !$access->isAllowed())continue;
      $languages=[];$county=NULL;$denied=FALSE;
      foreach(['field_lit_languages','field_lit_counties'] as $field)foreach($node->get($field)->referencedEntities() as $term){
        $cache->addCacheableDependency($term);$a=$term->access('view',$anonymous,TRUE);$cache->addCacheableDependency($a);
        if(!$a->isAllowed() || !$term->isPublished()){$denied=TRUE;continue;}
        if($field==='field_lit_languages')$languages[]=$term->field_lit_language_key->value;else $county=$term->field_lit_geoid->value;
      }
      if($denied || !$languages)continue;
      $kind=$node->field_lit_kind->value;$test=(bool)$node->field_lit_test->value;
      $verb=match($kind){'audio'=>'Listen to interview','video'=>'Watch interview',default=>'Open resource'};
      if($test)$verb=match($kind){'audio'=>'Listen to test recording','video'=>'Watch test recording',default=>'Open test resource'}.' (not a LiT interview)';
      $url=$node->field_lit_external->uri;$media=$node->field_lit_media->entity;$action=NULL;
      if($media){
        $cache->addCacheTags(['config:media.settings']);
        if(!\Drupal::config('media.settings')->get('standalone_url'))continue;
        $cache->addCacheableDependency($media);$a=$media->access('view',$anonymous,TRUE);$u=$media->toUrl('canonical')->access($anonymous,TRUE);$cache->addCacheableDependency($a)->addCacheableDependency($u);
        if(!$media->isPublished() || !$a->isAllowed() || !$u->isAllowed())continue;
        $action=$media->toLink($verb.': '.$node->label())->toRenderable();
      }elseif(self::safeUrl($url))$action=['#type'=>'link','#title'=>$verb.': '.$node->label(),'#url'=>Url::fromUri($url)];
      else continue;
      $action['#attributes']=['data-resource-link'=>'','rel'=>'noopener noreferrer'];
      $key=$node->field_lit_resource_key->value;
      $card=['#type'=>'container','#attributes'=>['class'=>['lit-bubbles__resource'],'data-resource-card'=>$key],
        'title'=>['#type'=>'html_tag','#tag'=>'h5','#value'=>\Drupal\Component\Utility\Html::escape($node->label())],
        'description'=>['#plain_text'=>$node->field_lit_description_long->value??''],'action'=>$action];
      if($test)$card['notice']=['#plain_text'=>'Test resource only — not a LiT interview.'];
      $transcript=$node->field_lit_transcript->uri;
      if(self::safeUrl($transcript))$card['transcript']=['#type'=>'link','#title'=>'Transcript / captions: '.$node->label(),'#url'=>Url::fromUri($transcript)];
      $cards[]=['id'=>$key,'county'=>$county,'municipality'=>$node->field_lit_municipality->value?:NULL,'languages'=>$languages,'card'=>$card];
    }
    return $cards;
  }
}
