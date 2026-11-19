<?php
/** Add the component to legacy installations which had no install/schema file. */
function lit_map_post_update_component_architecture(&$sandbox = NULL): string {
  \Drupal\lit_map\Service\ComponentSetup::install();
  return 'Additive component definitions verified. Seed terms and migrate resources using the explicit documented commands.';
}

/** Add external link controls; preserve all existing content and revisions. */
function lit_map_post_update_informational_fields(&$sandbox = NULL): string {
  \Drupal\lit_map\Service\ComponentSetup::install();
  $display=\Drupal\Core\Entity\Entity\EntityFormDisplay::load('paragraph.lit_map.default');
  if($display){$display->setComponent('field_lit_survey_show',['type'=>'boolean_checkbox','weight'=>9])->setComponent('field_lit_survey_url',['type'=>'link_default','weight'=>10])->save();}
  $field=\Drupal\field\Entity\FieldStorageConfig::loadByName('paragraph','field_lit_source');
  $field->setSetting('allowed_values',['informational'=>'Active informational dataset','published'=>'Active informational dataset (legacy value)'])->save();
  return 'Added optional HTTPS survey controls. Existing source values now select the active informational snapshot. No front-page or account changes.';
}
