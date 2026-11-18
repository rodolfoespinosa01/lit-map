<?php
namespace Drupal\lit_contribution\Service;

/** Provisional fixed-answer contract, not a confirmed research questionnaire. */
final class SubmissionValidator {
  public static function languages(): array { return \Drupal::service('lit_map.references')->languages(); }
  public const PROFICIENCY = ['none', 'basic', 'conversational', 'advanced', 'fluent', 'not_applicable', 'prefer_not_to_answer'];
  public const YES_NO = ['yes', 'no', 'prefer_not_to_answer'];
  public function __construct(private readonly string $appRoot) {}
  private ?array $referenceCache = NULL;
  private ?array $countyCache = NULL;
  public function references(): array {

    $refs = $this->referenceCache ?? json_decode(file_get_contents($this->appRoot . '/modules/custom/lit_map/data/survey-statewide-references.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $refs['languages'] = [];
    foreach (self::languages() as $id => $label) $refs['languages'][] = compact('id', 'label');
    return $this->referenceCache=$refs;
  }
  public function counties(): array {
    return \Drupal::service('lit_map.references')->counties();
  }
  private function require(bool $ok): void { if (!$ok) throw new \UnexpectedValueException('Submission does not match the provisional mock contract.'); }
  public static function normalizeEmail(string $email): string { return strtolower(trim($email)); }
  public function normalize(mixed $s, bool $synthetic = TRUE): array {
    if(is_array($s) && ($s['schema_version']??NULL)===2)return Questionnaire::normalize($s,$this);
    $this->require(is_array($s) && count($s) === 7 && !array_diff(array_keys($s), ['survey_id', 'response_id', 'submitted_at', 'updated_at', 'email', 'schema_version', 'answers']));
    $this->require($s['schema_version'] === 1);
    foreach (['survey_id', 'response_id'] as $key) $this->require(is_string($s[$key]) && preg_match('/^[A-Za-z0-9_-]{1,96}$/D', $s[$key]) === 1);
    foreach (['submitted_at', 'updated_at'] as $key) {
      $this->require(is_string($s[$key]) && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/D', $s[$key]) === 1);
      $offset = substr($s[$key], -5);
      $this->require((int) substr($offset, 0, 2) <= 14 && (int) substr($offset, 3, 2) <= 59 && ((int) substr($offset, 0, 2) < 14 || substr($offset, 3, 2) === '00'));
      $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $s[$key]);
      $this->require($date !== FALSE && $date->format('Y-m-d\TH:i:sP') === $s[$key]);
    }
    $this->require(strtotime($s['updated_at']) >= strtotime($s['submitted_at']));
    $this->require(is_string($s['email'])); $s['email'] = self::normalizeEmail($s['email']);
    $this->require(strlen($s['email']) <= 254 && filter_var($s['email'], FILTER_VALIDATE_EMAIL) !== FALSE && (!$synthetic || preg_match('/@(example\.org|example\.com|example\.net)$/D', $s['email']) === 1));
    $a = $s['answers'];
    $this->require(is_array($a) && count($a) === 5 && !array_diff(array_keys($a), ['county_geoid', 'municipality', 'first_languages', 'languages', 'aggregate_permission']));
    $this->require(is_string($a['county_geoid']) && in_array($a['county_geoid'], array_column($this->counties()['counties'], 'geoid'), TRUE));
    $towns = array_column($this->references()['municipalities'], NULL, 'id');
    $this->require($a['municipality'] === NULL || (is_string($a['municipality']) && isset($towns[$a['municipality']]) && $towns[$a['municipality']]['county'] === $a['county_geoid']));
    $this->require(in_array($a['aggregate_permission'], ['yes', 'no'], TRUE));
    $this->require(is_array($a['first_languages']) && array_is_list($a['first_languages']) && count(array_unique($a['first_languages'], SORT_REGULAR)) === count($a['first_languages']));
    foreach ($a['first_languages'] as $id) $this->require(is_string($id) && isset(self::languages()[$id]));
    sort($a['first_languages']);
    $this->require(is_array($a['languages']) && count($a['languages']) > 0 && !array_is_list($a['languages']));
    foreach ($a['languages'] as $id => &$profile) {
      $this->require(isset(self::languages()[$id]) && is_array($profile) && count($profile) === 7 && !array_diff(array_keys($profile), ['uses_language', 'speaking', 'signing', 'home_use', 'other_settings', 'frequency', 'brokering']));
      foreach (['uses_language', 'home_use'] as $key) $this->require(in_array($profile[$key], self::YES_NO, TRUE));
      foreach (['speaking', 'signing'] as $key) $this->require(in_array($profile[$key], self::PROFICIENCY, TRUE));
      $this->require(in_array($profile['frequency'], ['daily', 'weekly', 'monthly', 'less_often', 'never', 'prefer_not_to_answer'], TRUE));
      $this->require(in_array($profile['brokering'], ['never', 'occasionally', 'often', 'prefer_not_to_answer'], TRUE));
      $this->require(is_array($profile['other_settings']) && array_is_list($profile['other_settings']) && count(array_unique($profile['other_settings'], SORT_REGULAR)) === count($profile['other_settings']));
      foreach ($profile['other_settings'] as $setting) $this->require(in_array($setting, ['work', 'education', 'friends', 'community', 'online', 'religious', 'none', 'prefer_not_to_answer'], TRUE));
      if (in_array('none', $profile['other_settings'], TRUE) || in_array('prefer_not_to_answer', $profile['other_settings'], TRUE)) $this->require(count($profile['other_settings']) === 1);
      sort($profile['other_settings']); ksort($profile);
    }
    unset($profile); ksort($a['languages']); ksort($a); $s['answers'] = $a; ksort($s);
    return $s;
  }
  public function fingerprint(array $s): string {
    // A source bookkeeping timestamp alone is not a changed answer.
    unset($s['updated_at']);
    return hash('sha256', json_encode($s, JSON_THROW_ON_ERROR));
  }
}
