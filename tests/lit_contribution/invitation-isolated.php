<?php
// Isolated in-memory configuration. Never writes active configuration or follows URL.
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\ConfigFactory;
use Drupal\lit_contribution\Service\Invitation;
$before=\Drupal::config('lit_contribution.settings')->getRawData();$checks=0;
foreach([[FALSE,'https://test.qualtrics.com/jfe/form/SV_Isolated',FALSE],[TRUE,'',FALSE],[TRUE,'https://example.org/form',FALSE],[TRUE,'https://test.qualtrics.com/jfe/form/SV_Isolated',TRUE]] as [$enabled,$url,$expected]){
 $store=new MemoryStorage();$store->write('lit_contribution.settings',['invitation_enabled'=>$enabled,'survey_url'=>$url]);
 $factory=new ConfigFactory($store,new Symfony\Component\EventDispatcher\EventDispatcher(),\Drupal::service('config.typed'));
 $invitation=new Invitation($factory);
 if((bool)$invitation->url()!==$expected)throw new RuntimeException('Isolated invitation decision failed');$checks++;
 $build=['#theme'=>'lit_map_bubble_preview','#approved_preview'=>TRUE,'#available'=>TRUE,'#instance_id'=>'isolated-invitation','#invitation_url'=>$invitation->url()];
 $html=(string)\Drupal::service('renderer')->renderInIsolation($build);
 if(str_contains($html,'data-invitation')!==$expected || ($expected&&!str_contains($html,'Share your language experience')))throw new RuntimeException('Invitation Twig rendering failed');$checks++;
}
$settings=\Drupal\Core\Site\Settings::getAll();
try{foreach([['off',TRUE,FALSE],['local',FALSE,FALSE],['local',TRUE,TRUE],['external',FALSE,TRUE]] as [$mode,$development,$expected]){
 new \Drupal\Core\Site\Settings(array_replace($settings,['lit_local_demo_enabled'=>$development]));
 $store=new MemoryStorage();$store->write('lit_contribution.settings',['contribution_mode'=>$mode,'survey_url'=>'https://test.qualtrics.com/jfe/form/SV_Isolated']);
 $invitation=new Invitation(new ConfigFactory($store,new Symfony\Component\EventDispatcher\EventDispatcher(),\Drupal::service('config.typed')));
 if((bool)$invitation->url()!==$expected)throw new RuntimeException('Explicit mode/development gate failed');$checks++;
}}finally{new \Drupal\Core\Site\Settings($settings);}
if(\Drupal::config('lit_contribution.settings')->getRawData()!==$before)throw new RuntimeException('Active config changed');$checks++;
echo "$checks isolated invitation service/render/config-preservation checks passed. No URL contacted.\n";
