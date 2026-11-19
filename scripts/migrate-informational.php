<?php
/** Explicit migration, after private backup and extraction, before deleting old code. */
$b=getenv('LIT_MIGRATION_BACKUP')?:DRUPAL_ROOT.'/../private/informational-backup-20261119';
foreach(json_decode(file_get_contents($b.'/sha256.json'),TRUE,512,JSON_THROW_ON_ERROR) as $file=>$hash)if(!hash_equals($hash,hash_file('sha256',$b.'/'.$file)))throw new RuntimeException('Backup verification failed.');
if(!\Drupal::moduleHandler()->moduleExists('lit_contribution')){echo "Retired module already absent.\n";return;}
require __DIR__.'/migrate-lit-resources.php';
$d=json_decode(file_get_contents($b.'/active-dataset.json'),TRUE,512,JSON_THROW_ON_ERROR);
\Drupal::service('lit_map.dataset')->activate($d);
$roles=['lit_application_owner','lit_contribution_administrator','lit_contribution_reviewer'];
// Disable the former owner guard before normal Drupal role cleanup.
\Drupal::state()->delete('lit_contribution.owner_uid');
$permissions=array_keys(\Drupal\Component\Serialization\Yaml::decode(file_get_contents(DRUPAL_ROOT.'/modules/custom/lit_contribution/lit_contribution.permissions.yml')));
foreach(\Drupal\user\Entity\User::loadMultiple() as $user){$changed=FALSE;foreach($roles as $role)if($user->hasRole($role)){$user->removeRole($role);$changed=TRUE;}if($changed)$user->save();}
foreach(\Drupal\user\Entity\Role::loadMultiple() as $role){foreach($permissions as $p)$role->revokePermission($p);$role->save();}
// Drupal's uninstaller removes only this module's schema/config dependencies.
\Drupal::service('module_installer')->uninstall(['lit_contribution']);
foreach($roles as $id)if($role=\Drupal\user\Entity\Role::load($id))$role->delete();
$db=\Drupal::database();$keys=$db->select('key_value','k')->fields('k',['name'])->condition('collection','state')->condition('name',$db->escapeLike('lit_contribution.').'%', 'LIKE')->execute()->fetchCol();
foreach($keys as $key)\Drupal::state()->delete($key);
// Keep accounts: no account is presumed disposable from a name alone.
$before=json_decode(file_get_contents($b.'/entity-ids.json'),TRUE);foreach($before as $type=>$ids){$after=\Drupal::entityQuery($type)->accessCheck(FALSE)->execute();if(array_diff($ids,$after))throw new RuntimeException('Unexpected entity removal: '.$type);}
echo "Dataset migrated; contribution module/tables/config uninstalled; LiT roles removed. All existing entities and users preserved.\n";
