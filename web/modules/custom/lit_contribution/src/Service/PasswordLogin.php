<?php
namespace Drupal\lit_contribution\Service;
use Drupal\user\Entity\User;

/** Email identification; Drupal checks hashes and establishes the usual session. */
final class PasswordLogin {
  public function __construct(private readonly OwnerAccess $owner) {}
  public function authenticate(string $email,#[\SensitiveParameter] string $password,string $ip,string $mode): ?User {
    $email=strtolower(trim($email));$flood=\Drupal::flood();$key=hash('sha256',$email);$cfg=\Drupal::config('user.flood');
    $limit=(int)($cfg->get('ip_limit')??50);$window=(int)($cfg->get('ip_window')??3600);
    if(!$flood->isAllowed('lit.password.ip',$limit,$window,$ip) || !$flood->isAllowed('lit.password.email',5,900,$key))return NULL;
    $ids=filter_var($email,FILTER_VALIDATE_EMAIL)?\Drupal::entityQuery('user')->accessCheck(FALSE)->condition('mail',$email)->execute():[];
    $u=count($ids)===1?User::load(reset($ids)):NULL;
    $owner=$u && (int)$u->id()===$this->owner->ownerId() && $u->hasRole(OwnerAccess::OWNER_ROLE);
    $allowed=$u && $this->owner->authorized($u) && (($mode==='superadmin' && $owner) || ($mode==='administrator' && !$owner && $u->hasRole(OwnerAccess::ADMIN_ROLE)));
    $ok=FALSE;
    if($allowed && $password!=='' && strlen($password)<=128)$ok=\Drupal::service('user.auth')->authenticateAccount($u,$password);
    else { // Keep unknown/unauthorized accounts on the expensive hashing path.
      \Drupal::service('password')->hash('invalid-account-credential');
    }
    if(!$ok){$flood->register('lit.password.ip',$window,$ip);$flood->register('lit.password.email',900,$key);return NULL;}
    $flood->clear('lit.password.email',$key);return $u;
  }
}
