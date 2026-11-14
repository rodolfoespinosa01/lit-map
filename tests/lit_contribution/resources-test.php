<?php
/** In-memory config only. No changes to live catalogue, Media or survey records. */
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\ConfigFactory;
use Drupal\lit_contribution\Service\ResourceCatalog;
$store=new MemoryStorage();$factory=new ConfigFactory($store,new Symfony\Component\EventDispatcher\EventDispatcher(),\Drupal::service('config.typed'));
$service=new ResourceCatalog($factory,\Drupal::entityTypeManager(),\Drupal::service('lit_contribution.validator'));
$checks=0;$assert=function($ok,$why)use(&$checks){if(!$ok)throw new RuntimeException($why);$checks++;};
$doc=json_decode($service->json(),TRUE);$assert(count($service->validate(json_encode($doc)))===2,'Default placeholders');
$r=$doc['items'][0];$r['target']='external';$r['url']='https://www.w3.org/2010/05/video/mediaevents.html';$r['title']='W3C video demonstration — test only';$r['description']='External playback test; not a LiT interview.';$r['kind']='video';
$set=function($items)use($store,$factory){$store->write('lit_contribution.resources',['catalog_json'=>json_encode(['schema_version'=>1,'items'=>$items])]);$factory->reset('lit_contribution.resources');};
$set([$r]);$cards=$service->cards();$assert(count($cards)===1,'Approved HTTPS card');$html=(string)\Drupal::service('renderer')->renderInIsolation($cards[0]['card']);
$assert(str_contains($html,'Watch test recording (not a LiT interview)') && str_contains($html,'https://www.w3.org/2010/05/video/mediaevents.html'),'Drupal renders descriptive test link');
$audio=$r;$audio['kind']='audio';$audio['test_only']=FALSE;$audio['transcript_url']='https://example.org/transcript';$set([$audio]);$cards=$service->cards();$html=(string)\Drupal::service('renderer')->renderInIsolation($cards[0]['card']);
$assert(str_contains($html,'Listen to interview:'),'Audio action rendering (no actual audio URL contacted)');
$assert(str_contains($html,'Transcript / captions:') && str_contains($html,'https://example.org/transcript'),'Transcript link rendering (no actual transcript contacted)');
$draft=$r;$draft['approved']=FALSE;$set([$draft]);$assert($service->cards()===[],'Draft not publicly rendered');
$media=$r;$media['target']='media';$media['url']=NULL;$media['media_id']=999999;$set([$media]);$assert($service->cards()===[],'Unavailable or inaccessible Media fails closed');
foreach(['http://example.org/x','javascript:alert(1)','data:text/html,test','//example.org','https://user:pass@example.org','https://example.org\\@evil.test',"https://example.org/\n"] as $bad){$assert(!ResourceCatalog::safeUrl($bad),'Unsafe URL accepted');}
$assert(ResourceCatalog::safeUrl('https://vimeo.com/123456789'),'Vimeo HTTPS URL syntax supported (not contacted)');
foreach([
 ['url'=>'javascript:alert(1)'],['transcript_url'=>'http://example.org'],['media_id'=>1],['languages'=>['unknown']],['languages'=>['en','en']],['county'=>'99999'],['municipality'=>'unknown'],['approved'=>1],['id'=>'x" onclick=bad'],['target'=>'embed'],['title'=>''],['test_only'=>'yes']
] as $patch){try{$service->validate(json_encode(['schema_version'=>1,'items'=>[array_replace($r,$patch)]]));throw new LogicException('Invalid resource accepted');}catch(InvalidArgumentException $e){$checks++;}}
try{$service->validate(json_encode(['schema_version'=>1,'items'=>[$r,$r]]));throw new LogicException('Duplicate accepted');}catch(InvalidArgumentException $e){$checks++;}
$town=\Drupal::service('lit_contribution.validator')->references()['municipalities'][0];$mismatch=$r;$mismatch['municipality']=$town['id'];$mismatch['county']=$town['county']==='34003'?'34013':'34003';
try{$service->validate(json_encode(['schema_version'=>1,'items'=>[$mismatch]]));throw new LogicException('Mismatched town accepted');}catch(InvalidArgumentException $e){$checks++;}
$escape=$r;$escape['title']='<script>alert(1)</script>';$escape['description']='<img src=x onerror=bad>';$set([$escape]);$cards=$service->cards();$html=(string)\Drupal::service('renderer')->renderInIsolation($cards[0]['card']);$assert(!str_contains($html,'<script>') && !str_contains($html,'<img'),'Editorial text escaped');
$set($doc['items']);$cards=$service->cards();$html=(string)\Drupal::service('renderer')->renderInIsolation($cards[0]['card']);$assert(!str_contains($html,'href='),'Placeholder has no misleading link');
echo "$checks resource validation, approval, rendering and missing-Media checks passed. Actual Media rendering is untested: core Media is not enabled.\n";
