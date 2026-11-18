<?php
/** Only disposable hosts; controlled mutation/restore for anonymous HTTP cache tests. */
if(!in_array(getenv('DDEV_PROJECT'),['lit-component-d10','lit-component-d11'],TRUE))throw new RuntimeException('Disposable component host required.');
$mode=getenv('LIT_CACHE_TEST_MODE');$state=\Drupal::state();$storage=\Drupal::entityTypeManager()->getStorage('taxonomy_term');
$ids=$storage->getQuery()->accessCheck(FALSE)->condition('vid','lit_languages')->condition('field_lit_language_key','en')->execute();$term=$storage->load(reset($ids));
if($mode==='change'){$state->set('lit_component.cache_test_original_label',$term->label());$term->setName('English cache verification')->save();}
elseif($mode==='restore'){$label=$state->get('lit_component.cache_test_original_label');if($label){$term->setName($label)->save();$state->delete('lit_component.cache_test_original_label');}}
else throw new RuntimeException('Explicit change or restore required.');
echo "Editorial cache test mutation complete.\n";
