<?php
namespace Drupal\lit_map\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Canonical editorial references. Geometry and metrics remain separate. */
final class ReferenceProvider {
  public function __construct(private readonly EntityTypeManagerInterface $entities) {}
  private array $loaded=[];
  private array $derived=[];
  public function reset():void { $this->loaded=[];$this->derived=[]; }
  public function terms(string $vocabulary): array {
    if(isset($this->loaded[$vocabulary]))return $this->loaded[$vocabulary];
    $storage = $this->entities->getStorage('taxonomy_term');
    return $this->loaded[$vocabulary]=$storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->condition('vid', $vocabulary)->sort('weight')->sort('name')->execute());
  }
  public function languages(): array {
    if(isset($this->derived['languages']))return $this->derived['languages'];
    $result = [];
    foreach ($this->terms('lit_languages') as $term) {
      $key = $term->field_lit_language_key->value;
      if (!$key || isset($result[$key])) throw new \UnexpectedValueException('Missing or duplicate LiT language identifier.');
      $result[$key] = $term->label();
    }
    if (!$result) throw new \UnexpectedValueException('Run the explicit LiT reference seed command.');
    return $this->derived['languages']=$result;
  }
  public function counties(): array {
    if(isset($this->derived['counties']))return $this->derived['counties'];
    $result = [];
    foreach ($this->terms('lit_counties') as $term) {
      $key = $term->field_lit_geoid->value;
      if (!$key || isset($result[$key])) throw new \UnexpectedValueException('Missing or duplicate LiT county identifier.');
      $result[$key] = ['geoid'=>$key, 'key'=>$term->field_lit_map_key->value, 'name'=>$term->label()];
    }
    return $this->derived['counties']=['counties'=>array_values($result)];
  }
  public function resolvableLanguages(): array {
    $result=[];foreach($this->terms('lit_languages') as $term)if(!$term->field_lit_ambiguous->value)$result[$term->field_lit_language_key->value]=$term->label();return $result;
  }
  public function aliases(): array {
    if(isset($this->derived['aliases']))return $this->derived['aliases'];
    $result = [];
    foreach ($this->terms('lit_languages') as $term) {
      if ($term->field_lit_ambiguous->value) continue;
      foreach (array_merge([$term->label()], array_column($term->field_lit_aliases->getValue(), 'value')) as $alias) {
        $alias = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $alias)));
        $key = $term->field_lit_language_key->value;
        $result[$alias] = array_key_exists($alias, $result) && $result[$alias] !== $key ? NULL : $key;
      }
    }
    return $this->derived['aliases']=$result;
  }
}
