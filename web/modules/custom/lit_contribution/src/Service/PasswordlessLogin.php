<?php
namespace Drupal\lit_contribution\Service;
use Drupal\user\Entity\User;
/** Compatibility tombstone: old links/callers cannot authenticate or send mail. */
final class PasswordlessLogin {
  public function __construct(private readonly OwnerAccess $owner) {}
  public function request(string $email,string $ip): void { throw new \RuntimeException('LiT email-link authentication is disabled. Use email and password.'); }
  public function consume(#[\SensitiveParameter] string $token,string $ip): ?User { return NULL; }
}
