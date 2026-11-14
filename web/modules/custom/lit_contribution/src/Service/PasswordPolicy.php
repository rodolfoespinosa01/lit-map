<?php
namespace Drupal\lit_contribution\Service;
final class PasswordPolicy {
  public static function validate(#[\SensitiveParameter] string $password): void {
    $classes=(int)(bool)preg_match('/[a-z]/',$password)+(int)(bool)preg_match('/[A-Z]/',$password)+(int)(bool)preg_match('/[0-9]/',$password)+(int)(bool)preg_match('/[^a-zA-Z0-9\s]/',$password);
    $words=array_unique(preg_split('/[\s-]+/',strtolower($password)));
    if(strlen($password)<16 || strlen($password)>128 || trim($password)!==$password || preg_match('/[\x00-\x1f\x7f]/',$password) || !preg_match('/[a-zA-Z]/',$password) || !preg_match('/[0-9]/',$password) || ($classes<3 && count($words)<4) || preg_match('/(.)\1{7}/',$password))throw new \InvalidArgumentException('Use 16–128 characters with letters and digits, plus mixed case/symbols or at least four distinct words. No control characters or surrounding whitespace.');
  }
}
