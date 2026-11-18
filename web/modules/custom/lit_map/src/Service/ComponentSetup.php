<?php
namespace Drupal\lit_map\Service;

use Drupal\Core\Config\FileStorage;

/** Additive configuration only; taxonomy content is seeded separately. */
final class ComponentSetup {
  public static function install(): void {
    $source = new FileStorage(__DIR__.'/../../config/install');
    $active = \Drupal::service('config.storage');
    $pending = [];
    foreach ($source->listAll() as $name) {
      $data = $source->read($name);
      if ($active->exists($name)) {
        $old = $active->read($name);
        if(preg_match('/^(taxonomy\.vocabulary|node\.type|paragraphs\.paragraphs_type)\./',$name) && !in_array('lit_map',$old['dependencies']['enforced']['module']??[],TRUE))throw new \RuntimeException("Existing bundle is not owned by LiT; explicit reconciliation required: $name");
        if(isset($data['cardinality']) && ($old['cardinality']??NULL)!==$data['cardinality'])throw new \RuntimeException("Incompatible cardinality: $name");
        foreach (['type','entity_type','field_name','bundle','field_type','targetEntityType'] as $key) {
          if (isset($data[$key]) && ($old[$key]??NULL) !== $data[$key]) throw new \RuntimeException("Incompatible existing config: $name ($key)");
        }
        if(isset($data['settings']['handler_settings']['target_bundles']) && ($old['settings']['handler_settings']['target_bundles']??NULL)!==$data['settings']['handler_settings']['target_bundles'])throw new \RuntimeException("Incompatible target bundles: $name");
        if (isset($data['settings']['target_type']) && ($old['settings']['target_type']??NULL) !== $data['settings']['target_type']) throw new \RuntimeException("Incompatible reference target: $name");
        continue;
      }
      $pending[$name] = $data;
    }
    \Drupal::service('module_installer')->install(['taxonomy','node','text','options','link','media','paragraphs']);
    while ($pending) {
      $progress = FALSE;
      foreach ($pending as $name=>$data) {
        if (array_filter($data['dependencies']['config']??[], fn($dependency)=>!$active->exists($dependency))) continue;
        $type = \Drupal::service('config.manager')->getEntityTypeIdByName($name);
        if (!$type) throw new \RuntimeException("Unknown configuration entity: $name");
        if ($type === 'field_storage_config' && isset($data['settings']['allowed_values'])) $data['settings']['allowed_values'] = array_column($data['settings']['allowed_values'], 'label', 'value');
        \Drupal::entityTypeManager()->getStorage($type)->create($data)->save();
        unset($pending[$name]); $progress = TRUE;
      }
      if (!$progress) throw new \RuntimeException('Unresolved component configuration dependencies: '.implode(', ',array_keys($pending)));
    }
  }
}
