<?php
namespace Drupal\lit_contribution\Service;
use Drupal\user\Entity\User;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Application ownership is an explicit server-side UID, never a request role. */
final class OwnerAccess {
  public const OWNER_ROLE='lit_application_owner';
  public const ADMIN_ROLE='lit_contribution_administrator';
  public function ownerId(): int { return (int)\Drupal::state()->get('lit_contribution.owner_uid',0); }
  public function isOwner(): bool {
    $a=\Drupal::currentUser();$u=User::load($a->id());
    return $u && $u->isActive() && ((int)$u->id()===1 || ((int)$u->id()===$this->ownerId() && $u->hasRole(self::OWNER_ROLE)));
  }
  public function access(): AccessResult { return AccessResult::allowedIf($this->isOwner())->addCacheContexts(['user'])->setCacheMaxAge(0); }
  public function requireOwner(): void { if(!$this->isOwner())throw new AccessDeniedHttpException(); }
  public function authorized(User $u): bool { return $u->isActive() && (((int)$u->id()===$this->ownerId() && $u->hasRole(self::OWNER_ROLE)) || $u->hasRole(self::ADMIN_ROLE)); }
  public function audit(int $uid,string $action): void {
    \Drupal::database()->insert('lit_contribution_access_audit')->fields(['target_uid'=>$uid,'actor_uid'=>(int)\Drupal::currentUser()->id(),'action'=>$action,'occurred_at'=>time()])->execute();
  }
  public function add(string $email,#[\SensitiveParameter] string $password): int {
    $this->requireOwner();$email=strtolower(trim($email));
    PasswordPolicy::validate($password);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254)throw new \InvalidArgumentException('Enter a valid email address.');
    $ids=\Drupal::entityQuery('user')->accessCheck(FALSE)->condition('mail',$email)->execute();
    if(count($ids)>1)throw new \RuntimeException('Email is ambiguous; resolve accounts through server administration.');
    if($ids)throw new \RuntimeException('An account already uses that email. Use its administrator reset/reactivation controls, or reconcile authorization through server administration.');
    $u=User::create(['name'=>'lit-admin-'.bin2hex(random_bytes(8)),'mail'=>$email,'status'=>1]);$u->setPassword($password);
    $u->addRole(self::ADMIN_ROLE);$u->activate();$u->save();$this->audit((int)$u->id(),'administrator_authorized');return (int)$u->id();
  }
  public function resetPassword(int $uid,#[\SensitiveParameter] string $password): void {
    $this->requireOwner();PasswordPolicy::validate($password);$u=User::load($uid);
    if(!$u || !$u->hasRole(self::ADMIN_ROLE) || $uid===1 || $uid===$this->ownerId())throw new \RuntimeException('This account cannot be reset here.');
    $u->setPassword($password);$u->save();$this->revoke($uid);$this->audit($uid,'administrator_password_reset');
  }
  public function setActive(int $uid,bool $active): void {
    $this->requireOwner();$u=User::load($uid);
    if(!$u || (!$u->hasRole(self::ADMIN_ROLE) && !$u->hasRole('lit_contribution_reviewer')) || $uid===1 || $uid===$this->ownerId())throw new \RuntimeException('This account cannot be changed here.');
    $active?$u->activate():$u->block();$u->save();
    if(!$active)$this->revoke($uid);
    $this->audit($uid,$active?'administrator_reactivated':'administrator_deactivated');
  }
  public function revoke(int $uid): void {
    \Drupal::service('session_manager')->delete($uid);
    // Core SessionManager intentionally ignores CLI deletion. Explicit server
    // password resets must still revoke this account's persistent sessions.
    if(PHP_SAPI==='cli')\Drupal::database()->delete('sessions')->condition('uid',$uid)->execute();
    \Drupal::database()->delete('lit_contribution_login_token')->condition('uid',$uid)->execute();
  }
}
