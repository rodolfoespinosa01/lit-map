<?php
namespace Drupal\lit_contribution\Service;
use Drupal\Core\Config\ConfigFactoryInterface;
final class Invitation {
  public function __construct(private readonly ConfigFactoryInterface $config) {}
  public static function validUrl(string $url): bool {
    $p=parse_url($url);
    return filter_var($url,FILTER_VALIDATE_URL)!==FALSE && is_array($p) && ($p['scheme']??'')==='https' && preg_match('/^(?:[a-z0-9-]+\.)+qualtrics\.com$/iD',$p['host']??'')===1
      && preg_match('#^/jfe/form/SV_[A-Za-z0-9]+$#D',$p['path']??'')===1
      && !isset($p['user'],$p['pass']) && !isset($p['user']) && !isset($p['port']) && !isset($p['query']) && !isset($p['fragment']);
  }
  public function development():bool {return \Drupal\Core\Site\Settings::get('lit_local_demo_enabled',FALSE)===TRUE && getenv('IS_DDEV_PROJECT')==='true';}
  public function mode():string { $c=$this->config->get('lit_contribution.settings');return $c->get('contribution_mode')??($c->get('invitation_enabled')?'external':'off'); }
  public function localEnabled():bool {return $this->development() && $this->mode()==='local';}
  public function url(): ?string {
    if($this->localEnabled())return \Drupal\Core\Url::fromRoute('lit_contribution.demo')->toString();
    $c=$this->config->get('lit_contribution.settings');$url=(string)$c->get('survey_url');
    return $this->mode()==='external' && self::validUrl($url)?$url:NULL;
  }
}
