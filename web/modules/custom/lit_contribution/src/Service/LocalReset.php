<?php
namespace Drupal\lit_contribution\Service;
use Drupal\lit_contribution\Source\MockQualtricsSource;
use Drupal\lit_contribution\Source\PublishedBaselineAndExports;

/** Explicit local application-data reset. Never drops tables or deletes accounts. */
final class LocalReset {
  public const PRESETS=['contributions','fresh','empty'];
  private const DIRS=['lit-imports','lit-contribution','lit-questionnaire-10000-v2','lit-contribution-demo-10000-v1','union-qa'];
  private const ROLES=['lit_application_owner','lit_contribution_administrator','lit_contribution_reviewer'];
  private string $root;
  private $db;
  public function __construct(){$this->root=dirname(DRUPAL_ROOT);$this->db=\Drupal::database();}
  public function guard():void {
    if(PHP_SAPI!=='cli' || getenv('IS_DDEV_PROJECT')!=='true' || \Drupal\Core\Site\Settings::get('lit_local_demo_enabled',FALSE)!==TRUE || \Drupal\Core\Site\Settings::get('lit_local_reset_enabled',FALSE)!==TRUE)throw new \RuntimeException('Reset requires explicit local DDEV demo and reset settings.');
    $owner=\Drupal\user\Entity\User::load(\Drupal::service('lit_contribution.owner')->ownerId());
    if(!$owner || !$owner->isActive() || !$owner->hasRole(OwnerAccess::OWNER_ROLE))throw new \RuntimeException('A valid configured superadmin is required.');
    $this->safe($this->root.'/private');
  }
  private function safe(string $path):void {
    if(!str_starts_with($path,$this->root.'/private') || str_contains($path,'..'))throw new \RuntimeException('Out-of-scope storage.');
    $part=$this->root;
    foreach(explode('/',substr($path,strlen($this->root)+1)) as $segment){$part.='/'.$segment;if(is_link($part))throw new \RuntimeException('Symlink storage rejected.');if(file_exists($part) && !is_dir($part) && !is_file($part))throw new \RuntimeException('Special storage rejected.');}
  }
  private function files():array {
    $files=[];
    foreach(self::DIRS as $dir){$path=$this->root.'/private/'.$dir;$this->safe($path);if(!is_dir($path))continue;
      foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST) as $f){$this->safe($f->getPathname());if($f->isFile())$files[substr($f->getPathname(),strlen($this->root.'/private/'))]=$f->getPathname();}
    }ksort($files);return $files;
  }
  private function tables():array {\Drupal::moduleHandler()->loadInclude('lit_contribution','install');return array_keys(lit_contribution_schema());}
  private function admins():array {
    $uids=$this->db->select('user__roles','r')->fields('r',['entity_id'])->condition('roles_target_id',self::ROLES,'IN')->execute()->fetchCol();
    $uids=array_merge($uids,$this->db->select('lit_contribution_access_audit','a')->fields('a',['target_uid'])->execute()->fetchCol());
    $uids=array_merge($uids,$this->db->select('lit_contribution_login_token','t')->fields('t',['uid'])->execute()->fetchCol());
    return array_values(array_diff(array_unique(array_map('intval',$uids)),[0,1,\Drupal::service('lit_contribution.owner')->ownerId()]));
  }
  public function preview(string $preset):array {
    $this->guard();if(!in_array($preset,self::PRESETS,TRUE))throw new \RuntimeException('Explicit preset required.');
    $counts=[];foreach($this->tables() as $t)$counts[$t]=(int)$this->db->select($t,'t')->countQuery()->execute()->fetchField();
    $paths=array_fill_keys(array_map(fn($d)=>'private/'.$d,self::DIRS),0);foreach($this->files() as $rel=>$path)$paths['private/'.explode('/',$rel)[0]]++;
    return ['preset'=>$preset,'current_table_counts'=>$counts,'administrators_to_revoke'=>$preset==='contributions'?0:count($this->admins()),'scoped_file_counts'=>$paths,'baseline'=>$preset==='fresh'?MockQualtricsSource::QUESTIONNAIRE:($preset==='empty'?'none':PublishedBaselineAndExports::baselineSurvey())];
  }
  public function backupOnly(string $preset):string {
    $this->preview($preset);$gate=LocalOperationGate::acquire(TRUE);
    try{return $this->backup($this->snapshot($preset));}finally{fclose($gate);}
  }
  public function previewRestore(string $id):array {
    $this->guard();$d=$this->readBackup($id);
    return ['preset'=>$d['preset'],'table_counts'=>array_map('count',$d['tables']),'file_count'=>count($d['files']),'restores_administrator_access'=>count($d['admins'])];
  }
  private function snapshot(string $preset):array {
    $data=['version'=>1,'site'=>hash('sha256',$this->root.\Drupal::service('private_key')->get()),'preset'=>$preset,'admins'=>$preset==='contributions'?[]:$this->admins(),'tables'=>[],'files'=>[]];
    foreach($this->tables() as $t)$data['tables'][$t]=$this->db->select($t,'t')->fields('t')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach(['user__roles'=>'entity_id','sessions'=>'uid'] as $t=>$key)$data['tables'][$t]=($data['admins'] && $this->db->schema()->tableExists($t))?$this->db->select($t,'t')->fields('t')->condition($key,$data['admins'],'IN')->execute()->fetchAll(\PDO::FETCH_ASSOC):[];
    $data['tables']['user__roles']=array_values(array_filter($data['tables']['user__roles'],fn($r)=>in_array($r['roles_target_id'],self::ROLES,TRUE)));
    $data['tables']['config']=$this->db->select('config','c')->fields('c')->condition('name','lit_contribution.settings')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $data['tables']['key_value']=$this->db->select('key_value','k')->fields('k')->condition('collection','state')->condition('name',['lit_contribution.baseline','lit_contribution.active_dataset'],'IN')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach($this->files() as $rel=>$path){$b=file_get_contents($path);if($b===FALSE)throw new \RuntimeException('Backup source unreadable.');$data['files'][$rel]=$b;}
    return $data;
  }
  private function backup(array $data):string {
    $dir=$this->root.'/private/lit-reset-backups';$this->safe($dir);if(!is_dir($dir) && !mkdir($dir,0700))throw new \RuntimeException('Backup directory unavailable.');if(!chmod($dir,0700))throw new \RuntimeException('Private backup permissions unavailable.');
    $id=gmdate('Ymd-His').'-'.bin2hex(random_bytes(8));$path=$dir.'/'.$id.'.backup';$bytes=serialize($data);$h=fopen($path,'xb');if(!$h)throw new \RuntimeException('Backup unavailable.');if(!chmod($path,0600)){fclose($h);throw new \RuntimeException('Private backup permissions unavailable.');}
    try{if(fwrite($h,$bytes)!==strlen($bytes) || !fflush($h) || !fsync($h))throw new \RuntimeException('Backup flush failed.');}finally{fclose($h);}
    if(!hash_equals(hash('sha256',$bytes),hash_file('sha256',$path)))throw new \RuntimeException('Backup verification failed.');
    if(file_put_contents($path.'.sha256',hash('sha256',$bytes))!==64 || !chmod($path.'.sha256',0600))throw new \RuntimeException('Backup checksum storage failed.');
    if($this->readBackup($id)!==$data)throw new \RuntimeException('Backup round-trip failed.');return $id;
  }
  private function readBackup(string $id):array {
    if(!preg_match('/^\d{8}-\d{6}-[a-f0-9]{16}$/D',$id))throw new \RuntimeException('Invalid backup identifier.');
    $path=$this->root.'/private/lit-reset-backups/'.$id.'.backup';$this->safe($path);$this->safe($path.'.sha256');$b=file_get_contents($path);$hash=file_get_contents($path.'.sha256');
    if(!$b || !$hash || !hash_equals($hash,hash('sha256',$b)))throw new \RuntimeException('Backup checksum mismatch.');
    $d=unserialize($b,['allowed_classes'=>FALSE]);
    if(!is_array($d) || ($d['version']??0)!==1 || ($d['site']??'')!==hash('sha256',$this->root.\Drupal::service('private_key')->get()) || array_keys($d['tables'])!==array_merge($this->tables(),['user__roles','sessions','config','key_value']))throw new \RuntimeException('Backup scope/site mismatch.');
    foreach($d['files'] as $rel=>$bytes){if(!in_array(explode('/',$rel)[0],self::DIRS,TRUE))throw new \RuntimeException('Backup file scope mismatch.');$this->safe($this->root.'/private/'.$rel);}
    return $d;
  }
  private function writeFile(string $path,string $bytes):void {
    $this->safe($path);if(!is_dir(dirname($path)) && !mkdir(dirname($path),0700,TRUE))throw new \RuntimeException('Storage unavailable.');
    $tmp=$path.'.reset-'.bin2hex(random_bytes(8));$h=fopen($tmp,'xb');chmod($tmp,0600);
    try{if(fwrite($h,$bytes)!==strlen($bytes) || !fflush($h) || !fsync($h))throw new \RuntimeException('Incomplete restored file.');}finally{fclose($h);}
    if(!rename($tmp,$path))throw new \RuntimeException('Restore rename failed.');
  }
  private function restoreData(array $data):void {
    // Restore files before pointers; readers remain fenced until all work succeeds.
    foreach($data['files'] as $rel=>$bytes)$this->writeFile($this->root.'/private/'.$rel,$bytes);
    $tx=$this->db->startTransaction();
    try{foreach($data['tables'] as $t=>$rows){if($t==='sessions' && !$this->db->schema()->tableExists($t) && !$rows)continue;$q=$this->db->delete($t);
      if($t==='user__roles' || $t==='sessions'){if(!$data['admins'])continue;$q->condition($t==='sessions'?'uid':'entity_id',$data['admins'],'IN');if($t==='user__roles')$q->condition('roles_target_id',self::ROLES,'IN');}
      elseif($t==='config')$q->condition('name','lit_contribution.settings');
      elseif($t==='key_value')$q->condition('collection','state')->condition('name',['lit_contribution.baseline','lit_contribution.active_dataset'],'IN');
      $q->execute();foreach($rows as $row){
        // Drupal may renumber unrelated role deltas after an account save.
        // Restore membership without overwriting those unrelated role rows.
        if($t==='user__roles'){
          $last=$this->db->select($t,'r')->fields('r',['delta'])->condition('entity_id',$row['entity_id'])->condition('deleted',$row['deleted'])->condition('langcode',$row['langcode'])->orderBy('delta','DESC')->range(0,1)->execute()->fetchField();
          $row['delta']=$last===FALSE?0:(int)$last+1;
        }
        $this->db->insert($t)->fields($row)->execute();
      }
    }unset($tx);}catch(\Throwable $e){$tx->rollBack();throw $e;}
    foreach($this->files() as $rel=>$path)if(!isset($data['files'][$rel]) && !unlink($path))throw new \RuntimeException('Restore cleanup failed.');
    $generated=$this->root.'/private/lit-questionnaire-10000-v2/reset-generated';if(is_dir($generated) && !glob($generated.'/*'))rmdir($generated);
    $this->clearCaches();
  }
  private function clearCaches():void {\Drupal::state()->resetCache();\Drupal::configFactory()->reset();\Drupal::entityTypeManager()->getStorage('user')->resetCache();\Drupal::service('cache_tags.invalidator')->invalidateTags(['config:lit_contribution.settings','user_list','lit_map:publication']);foreach(['cache.render','cache.page','cache.dynamic_page_cache'] as $id)if(\Drupal::hasService($id))\Drupal::service($id)->deleteAll();}
  public function run(string $preset):string {
    $this->preview($preset);$gate=LocalOperationGate::acquire(TRUE);
    $marker=$this->root.'/private/lit-reset-incomplete';$this->safe($marker);if(file_exists($marker))throw new \RuntimeException('Restore the interrupted reset first.');
    try{
      $data=$this->snapshot($preset);$id=$this->backup($data);$this->writeFile($marker,$id);
      try{$this->mutate($preset);$this->clearCaches();if(!unlink($marker))throw new \RuntimeException('Recovery marker cleanup failed.');}
      catch(\Throwable $e){try{$this->restoreData($data);unlink($marker);}catch(\Throwable $recovery){throw new \RuntimeException('Reset failed; explicit restore required. Backup: '.$id);}throw new \RuntimeException('Reset failed; original data restored. Backup: '.$id);}
      return $id;
    }finally{fclose($gate);}
  }
  public function restore(string $id):string {
    $this->guard();$gate=LocalOperationGate::acquire(TRUE);
    try{$data=$this->readBackup($id);$before=$this->backup($this->snapshot($data['preset']));$marker=$this->root.'/private/lit-reset-incomplete';$this->writeFile($marker,$id);$this->restoreData($data);if(!unlink($marker))throw new \RuntimeException('Recovery marker cleanup failed.');return $before;}finally{fclose($gate);}
  }
  private function mutate(string $preset):void {
    $baseline=$preset==='empty'?'none':($preset==='fresh'?MockQualtricsSource::QUESTIONNAIRE:PublishedBaselineAndExports::baselineSurvey());
    if($preset==='fresh'){
      // Generate into a new owned directory; never reuse edited historical sources.
      $out=$this->root.'/private/lit-questionnaire-10000-v2/reset-generated';$this->safe($out);
      if(file_exists($out))throw new \RuntimeException('Stale generation directory; recover first.');
      $process=new \Symfony\Component\Process\Process(['python3',$this->root.'/tools/contribution/generate-questionnaire-demo.py',$out]);$process->setTimeout(120);$process->mustRun();
      foreach(['submissions.json','manifest.json'] as $file)$this->writeFile(dirname($out).'/'.$file,file_get_contents($out.'/'.$file));
    }
    $snapshot=$baseline==='none'?[]:array_filter(\Drupal::service('lit_contribution.mock_source')->load($baseline),fn($s)=>preg_match('/^R_(?:QV2|LARGE)_\d{5}$/D',$s['response_id']) && (int)substr($s['response_id'],-5)<=10000);
    if($baseline!=='none' && count($snapshot)!==10000)throw new \RuntimeException('Baseline must contain exactly 10,000 responses.');
    $tx=$this->db->startTransaction();
    try{
      if($preset==='contributions'){
        $ids=$this->db->select('lit_contribution_review','r')->fields('r',['id'])->condition('origin','upload')->execute()->fetchCol();
        if($ids){$this->db->delete('lit_contribution_audit')->condition('review_id',$ids,'IN')->execute();$this->db->delete('lit_contribution_review')->condition('id',$ids,'IN')->execute();}
      }else{
        foreach(['lit_contribution_audit','lit_contribution_review','lit_contribution_dataset'] as $t)$this->db->delete($t)->execute();
        $admins=$this->admins();if($admins){$this->db->delete('user__roles')->condition('entity_id',$admins,'IN')->condition('roles_target_id',self::ROLES,'IN')->execute();foreach(['sessions','lit_contribution_login_token'] as $t)if($this->db->schema()->tableExists($t))$this->db->delete($t)->condition('uid',$admins,'IN')->execute();}
        $this->db->delete('lit_contribution_access_audit')->execute();
        \Drupal::configFactory()->getEditable('lit_contribution.settings')->set('contribution_mode','off')->set('invitation_enabled',FALSE)->set('survey_url','')->save();
        \Drupal::state()->set('lit_contribution.baseline',$baseline);\Drupal::state()->set('lit_contribution.active_dataset',$baseline);
        foreach($snapshot as $s)$this->db->insert('lit_contribution_review')->fields(['survey_id'=>$s['survey_id'],'response_id'=>$s['response_id'],'fingerprint'=>\Drupal::service('lit_contribution.validator')->fingerprint($s),'email_digest'=>hash_hmac('sha256',$s['survey_id'].'\0'.$s['email'],\Drupal::service('private_key')->get()),'status'=>'approved','source_present'=>1,'submitted_at'=>$s['submitted_at'],'submitted_day'=>SubmissionDates::day($s['submitted_at'])??'','submitted_epoch'=>strtotime($s['submitted_at']),'source_updated_at'=>$s['updated_at'],'reason'=>'Reproducible synthetic demonstration baseline.','reviewed_at'=>time()])->execute();
      }
      foreach(['lit_contribution_email_reservation','lit_contribution_import_history','lit_contribution_import_state'] as $t)$this->db->delete($t)->execute();
      $rows=$baseline==='none'?[]:$this->db->select('lit_contribution_review','r')->fields('r')->condition('survey_id',$baseline)->execute()->fetchAll(\PDO::FETCH_ASSOC);
      $result=AggregateBuilder::build($snapshot,$rows);$validator=\Drupal::service('lit_contribution.validator');\Drupal::service('lit_map.survey_normalizer')->normalize($result['aggregate'],$validator->counties(),$validator->references());
      $writer=new AtomicAggregateWriter(DRUPAL_ROOT);$panels=$writer->write(['cohorts'=>$result['questionnaire']]);$hash=$writer->write(['publication_version'=>3,'updated_date'=>gmdate('Y-m-d'),'aggregate'=>$result['aggregate'],'questionnaire_hash'=>$panels]);
      $this->db->delete('lit_contribution_publication')->execute();$this->db->insert('lit_contribution_publication')->fields(['id'=>1,'file_hash'=>$hash,'published_at'=>time(),'publisher_uid'=>\Drupal::service('lit_contribution.owner')->ownerId()])->execute();
      $this->db->update('lit_contribution_review')->fields(['in_publication'=>0])->execute();if($result['included'])$this->db->update('lit_contribution_review')->fields(['in_publication'=>1,'published_at'=>time()])->condition('id',$result['included'],'IN')->execute();
      // Historical dataset pointers must not retain obsolete publications.
      $this->db->update('lit_contribution_dataset')->fields(['file_hash'=>'','published_at'=>0])->execute();
      if($baseline!=='none')$this->db->merge('lit_contribution_dataset')->keys(['survey_id'=>$baseline])->fields(['seeded'=>1,'file_hash'=>$hash,'published_at'=>time()])->execute();
      unset($tx);
    }catch(\Throwable $e){$tx->rollBack();throw $e;}
    $keep=['lit-contribution/'.$hash.'.json'=>TRUE,'lit-contribution/'.$panels.'.json'=>TRUE];
    foreach($this->files() as $rel=>$path){$dir=explode('/',$rel)[0];$preserve=isset($keep[$rel]) || ($preset==='contributions' && in_array($dir,['lit-questionnaire-10000-v2','lit-contribution-demo-10000-v1'],TRUE)) || ($preset==='fresh' && in_array($rel,['lit-questionnaire-10000-v2/submissions.json','lit-questionnaire-10000-v2/manifest.json'],TRUE));if(!$preserve && !unlink($path))throw new \RuntimeException('Scoped cleanup failed.');}
    $generated=$this->root.'/private/lit-questionnaire-10000-v2/reset-generated';if(is_dir($generated))rmdir($generated);
  }
}
