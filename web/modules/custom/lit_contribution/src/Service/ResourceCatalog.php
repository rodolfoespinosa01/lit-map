<?php
namespace Drupal\lit_contribution\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;

/** Editorial resources, deliberately independent of submissions and publication. */
final class ResourceCatalog {
  public function __construct(private readonly ConfigFactoryInterface $config, private readonly EntityTypeManagerInterface $entities, private readonly SubmissionValidator $references) {}

  public static function safeUrl(mixed $url): bool {
    if(!is_string($url) || strlen($url)>2048 || preg_match('/[\x00-\x20\x7f\\\\]/',$url) || !filter_var($url,FILTER_VALIDATE_URL))return FALSE;
    $p=parse_url($url);
    return $p && ($p['scheme']??'')==='https' && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']);
  }
  public function json(): string {
    return $this->config->get('lit_contribution.resources')->get('catalog_json') ?: file_get_contents(__DIR__.'/../../data/resources.demo.v1.json');
  }
  public function validate(string $json): array {
    if(strlen($json)>250000)throw new \InvalidArgumentException('Resource catalogue exceeds 250 KB.');
    $doc=json_decode($json,TRUE,32,JSON_THROW_ON_ERROR);
    if(!is_array($doc) || count($doc)!==2 || ($doc['schema_version']??NULL)!==1 || !isset($doc['items']) || !is_array($doc['items']) || !array_is_list($doc['items']) || count($doc['items'])>200)throw new \InvalidArgumentException('Expected schema_version 1 and an items list (maximum 200).');
    $refs=$this->references->references();$languages=array_column($refs['languages'],NULL,'id');$towns=array_column($refs['municipalities'],NULL,'id');$counties=array_column($this->references->counties()['counties'],NULL,'geoid');$seen=[];
    $fields=['id','approved','title','description','languages','county','municipality','kind','target','url','media_id','transcript_url','test_only'];
    foreach($doc['items'] as $r){
      if(!is_array($r) || count($r)!==count($fields) || array_diff(array_keys($r),$fields))throw new \InvalidArgumentException('Each resource must contain exactly the documented fields.');
      if(!is_string($r['id']) || !preg_match('/^[a-z][a-z0-9-]{0,63}$/D',$r['id']) || isset($seen[$r['id']]))throw new \InvalidArgumentException('Resource IDs must be unique lowercase keys.');$seen[$r['id']]=TRUE;
      foreach(['title'=>160,'description'=>1200] as $field=>$limit)if(!is_string($r[$field]) || trim($r[$field])==='' || strlen($r[$field])>$limit)throw new \InvalidArgumentException('Provide a concise title and description.');
      if(!is_bool($r['approved']) || !is_bool($r['test_only']) || !in_array($r['kind'],['audio','video','resource'],TRUE) || !in_array($r['target'],['placeholder','external','media'],TRUE))throw new \InvalidArgumentException('Invalid resource approval, kind, target or test flag.');
      if(!is_array($r['languages']) || !array_is_list($r['languages']) || !$r['languages'])throw new \InvalidArgumentException('Associate at least one language.');
      foreach($r['languages'] as $l)if(!is_string($l) || !isset($languages[$l]))throw new \InvalidArgumentException('Unknown language.');
      if(count(array_unique($r['languages']))!==count($r['languages']))throw new \InvalidArgumentException('Duplicate resource language.');
      if($r['county']!==NULL && (!is_string($r['county']) || !isset($counties[$r['county']])))throw new \InvalidArgumentException('Unknown county.');
      if($r['municipality']!==NULL && (!is_string($r['municipality']) || !isset($towns[$r['municipality']]) || $towns[$r['municipality']]['county']!==$r['county']))throw new \InvalidArgumentException('Municipality must belong to the resource county.');
      if($r['transcript_url']!==NULL && !self::safeUrl($r['transcript_url']))throw new \InvalidArgumentException('Transcript/caption URL must be HTTPS without credentials.');
      if($r['target']==='external' && (!self::safeUrl($r['url']) || $r['media_id']!==NULL))throw new \InvalidArgumentException('External resources need a safe HTTPS URL and no Media ID.');
      if($r['target']==='media' && (!is_int($r['media_id']) || $r['media_id']<1 || $r['url']!==NULL))throw new \InvalidArgumentException('Media resources need a positive Media ID and no external URL.');
      if($r['target']==='placeholder' && ($r['url']!==NULL || $r['media_id']!==NULL || $r['transcript_url']!==NULL || !$r['test_only']))throw new \InvalidArgumentException('Placeholders must be marked test_only and have no links.');
    }
    return $doc['items'];
  }
  /** Render only approved public cards, even when the visitor is the owner. */
  public function cards(): array {
    $cards=[];$anonymous=new AnonymousUserSession();
    foreach($this->validate($this->json()) as $r){
      if(!$r['approved'])continue;
      $verb=match($r['kind']){'audio'=>'Listen to interview','video'=>'Watch interview',default=>'Open resource'};
      if($r['test_only'])$verb=match($r['kind']){'audio'=>'Listen to test recording','video'=>'Watch test recording',default=>'Open test resource'}.' (not a LiT interview)';
      $action=NULL;
      if($r['target']==='external')$action=['#type'=>'link','#title'=>$verb.': '.$r['title'],'#url'=>Url::fromUri($r['url']),'#attributes'=>['rel'=>'noopener noreferrer','data-resource-link'=>'']];
      elseif($r['target']==='media'){
        // Core Media is optional. Never grant permissions or enable modules here.
        if(!$this->entities->hasDefinition('media'))continue;
        $media=$this->entities->getStorage('media')->load($r['media_id']);
        if(!$media || !$media->isPublished() || !$media->access('view',$anonymous) || !$media->toUrl('canonical')->access($anonymous))continue;
        $action=$media->toLink($verb.': '.$r['title'])->toRenderable();
        $action['#attributes']['data-resource-link']='';
        $action['#cache']['tags']=$media->getCacheTags();
      }
      $card=['#type'=>'container','#attributes'=>['class'=>['lit-bubbles__resource'],'data-resource-card'=>$r['id']]];
      $card['title']=['#type'=>'html_tag','#tag'=>'h5','#value'=>\Drupal\Component\Utility\Html::escape($r['title'])];
      if($r['test_only'])$card['notice']=['#type'=>'html_tag','#tag'=>'p','#value'=>$r['target']==='placeholder'?'Demonstration placeholder — no recording or interview is attached.':'Test resource only — not a LiT interview.'];
      $card['description']=['#type'=>'container','text'=>['#plain_text'=>$r['description']]];
      if($action)$card['action']=$action;
      if($r['transcript_url'])$card['transcript']=['#type'=>'link','#title'=>'Transcript / captions: '.$r['title'],'#url'=>Url::fromUri($r['transcript_url']),'#prefix'=>'<p>','#suffix'=>'</p>','#attributes'=>['rel'=>'noopener noreferrer']];
      $cards[]=['id'=>$r['id'],'languages'=>$r['languages'],'county'=>$r['county'],'municipality'=>$r['municipality'],'card'=>$card];
    }
    return $cards;
  }
}
