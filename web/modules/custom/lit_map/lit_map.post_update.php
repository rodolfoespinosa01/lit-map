<?php
/** Add the component to legacy installations which had no install/schema file. */
function lit_map_post_update_component_architecture(&$sandbox = NULL): string {
  \Drupal\lit_map\Service\ComponentSetup::install();
  return 'Additive component definitions verified. Seed terms and migrate resources using the explicit documented commands.';
}
