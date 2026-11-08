<?php
/** One-time company directory cleanup. Dry run by default; --apply creates a private snapshot first. */
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$names=['Judibas GOC','Skyinfinit','Cruxlo','Wecoma','Bedizen','NLTPL','China bedding store','Yubis','Sincwell','Suivillion','Corzella','True North','Oxyien','Judibas Store','Judibas Canteen','True Water','Gaming Lounge','Backend - FSD','Sales Office - FSD','Sales Office - MUL'];
$aliases=['Judibas'=>'Judibas GOC','Judibass'=>'Judibas GOC','Oxygen'=>'Oxyien'];
$rehearse=in_array('--rehearse',$argv,true);
$apply=in_array('--apply',$argv,true)||$rehearse;
$companies=DB::table('communication_companies')->orderBy('id')->get();
$keep=[];$merge=[];$remove=[];
foreach($companies as $c){
 $name=$aliases[$c->name]??collect($names)->first(fn($n)=>strcasecmp($n,trim($c->name))===0);
 if(!$name){$remove[$c->id]=$c->name;continue;}
 if(isset($keep[$name])){$merge[$c->id]=$keep[$name];}else $keep[$name]=$c->id;
}
// Prefer the existing CV Bank owner so the published careers page and legacy defaults stay stable.
$cvOwner=DB::table('cv_bank_settings')->where('id',1)->value('company_id');
if($cvOwner&&isset($merge[$cvOwner])){$old=$merge[$cvOwner];unset($merge[$cvOwner]);$merge[$old]=$cvOwner;$keep['Judibas GOC']=$cvOwner;}
$legacyPms=DB::table('pms_companies')->whereNull('platform_company_id')->get(['id','name']);
foreach($remove as $name)if(!preg_match('/\b(test|demo)\b/i',$name))throw new RuntimeException('Unrecognised non-test company requires review: '.$name);
foreach($legacyPms as $c)if(!in_array($c->name,['Cedar Retail','Northstar Foods'],true))throw new RuntimeException('Unrecognised legacy PMS company requires review: '.$c->name);
$plan=['retain_or_create'=>$names,'merge_company_ids'=>$merge,'remove_test_companies'=>$remove,'remove_legacy_pms_companies'=>$legacyPms];
echo json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
if(!$apply){echo "Dry run only. No data changed.\n";exit;}

DB::beginTransaction();
try{
 DB::statement("SET LOCAL lock_timeout = '10s'");
 $preservedCounts=[];foreach(['users','canteen_orders','canteen_order_lines','canteen_items','canteen_profiles','cv_bank_applications','cv_bank_jobs','cv_bank_departments','cv_bank_settings'] as $table)$preservedCounts[$table]=DB::table($table)->count();
 $retainedAccounting=DB::table('accounting_journals')->whereNotIn('company_id',array_keys($remove))->orderBy('id')->get()->toJson();
 $tables=DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename");
 $quote=fn($s)=>'"'.str_replace('"','""',$s).'"';
 // Prevent concurrent writes between the backup, merge and cleanup.
 DB::statement('LOCK TABLE '.implode(',',array_map(fn($t)=>$quote($t->tablename),$tables)).' IN SHARE ROW EXCLUSIVE MODE');
 $current=DB::table('communication_companies')->orderBy('id')->get();
 if($current->toJson()!==$companies->toJson())throw new RuntimeException('Company directory changed during preflight; rerun the dry run.');
 $dir=storage_path('app/private/company-cleanup');if(!is_dir($dir))mkdir($dir,0700,true);
 $backup=$dir.'/before-cleanup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.jsonl.gz';
 $gz=gzopen($backup,'wb9');if(!$gz)throw new RuntimeException('Cannot create private data snapshot.');
 $write=function($row)use($gz){$line=json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";if(gzwrite($gz,$line)!==strlen($line))throw new RuntimeException('Backup write failed.');};
 try{
  $write(['format'=>'judibas-data-snapshot-v1','created_at'=>now()->toIso8601String(),'plan'=>$plan]);
  foreach($tables as $t){$table=$t->tablename;$columns=DB::select("SELECT column_name,data_type,udt_name,column_default,is_nullable FROM information_schema.columns WHERE table_schema='public' AND table_name=? ORDER BY ordinal_position",[$table]);$write(['table'=>$table,'columns'=>$columns]);foreach(DB::table($table)->cursor() as $row)$write(['table'=>$table,'row'=>(array)$row]);}
  $write(['sequences'=>DB::select("SELECT schemaname,sequencename,last_value FROM pg_sequences WHERE schemaname='public'")]);$write(['complete'=>true]);
 }finally{gzclose($gz);}
 // Verify the archive can be read back completely before changing records.
 $read=gzopen($backup,'rb');$last=null;while(!gzeof($read)){$line=gzgets($read);if($line!==false&&trim($line)!=='')$last=json_decode($line,true,512,JSON_THROW_ON_ERROR);}gzclose($read);
 if(!($last['complete']??false))throw new RuntimeException('Snapshot verification failed.');
 file_put_contents($backup.'.sha256',hash_file('sha256',$backup).PHP_EOL);

 $fks=DB::select("SELECT c.conname,c.conrelid::regclass::text child,c.confrelid::regclass::text parent,c.condeferrable,c.condeferred,array_to_json(ARRAY(SELECT attname FROM unnest(c.conkey) WITH ORDINALITY k(num,ord) JOIN pg_attribute a ON a.attrelid=c.conrelid AND a.attnum=k.num ORDER BY ord))::text child_columns,array_to_json(ARRAY(SELECT attname FROM unnest(c.confkey) WITH ORDINALITY k(num,ord) JOIN pg_attribute a ON a.attrelid=c.confrelid AND a.attnum=k.num ORDER BY ord))::text parent_columns FROM pg_constraint c JOIN pg_namespace n ON n.oid=c.connamespace WHERE c.contype='f' AND n.nspname='public'");
 foreach($fks as $fk)DB::statement('ALTER TABLE '.$quote($fk->child).' ALTER CONSTRAINT '.$quote($fk->conname).' DEFERRABLE INITIALLY DEFERRED');
 DB::statement('SET CONSTRAINTS ALL DEFERRED');
 $columns=DB::select("SELECT table_name,column_name FROM information_schema.columns WHERE table_schema='public' AND column_name IN ('company_id','platform_company_id') ORDER BY table_name");
 foreach($merge as $from=>$to){
  // Duplicate PMS mirrors have their project/client references merged before removing the empty mirror.
  $source=DB::table('pms_companies')->where('platform_company_id',$from)->first();$target=DB::table('pms_companies')->where('platform_company_id',$to)->first();
  if($source&&$target){foreach($fks as $fk)if($fk->parent==='pms_companies'){ $cc=json_decode($fk->child_columns,true);$pc=json_decode($fk->parent_columns,true);if($pc===['id'])DB::table($fk->child)->where($cc[0],$target->id)->update([$cc[0]=>$source->id]);}DB::table('pms_companies')->where('id',$target->id)->delete();}
  foreach($columns as $column){$table=$column->table_name;$field=$column->column_name;if($table==='pms_companies'&&$field==='platform_company_id'||!str_starts_with($table,'pms_')&&$field==='company_id'){
    if($table==='communication_company_memberships'){foreach(DB::table($table)->where($field,$from)->get() as $member){$row=(array)$member;$row[$field]=$to;DB::table($table)->insertOrIgnore($row);}DB::table($table)->where($field,$from)->delete();}
    else DB::table($table)->where($field,$from)->update([$field=>$to]);
  }}
  foreach($columns as $column){if($column->column_name!=='company_id'||str_starts_with($column->table_name,'pms_'))continue;$default=DB::table('information_schema.columns')->where('table_schema','public')->where('table_name',$column->table_name)->where('column_name','company_id')->value('column_default');if($default!==null&&preg_match('/^\(?[\']?'.preg_quote((string)$from,'/').'[\']?\)?(?:::.*)?$/',trim($default)))DB::statement('ALTER TABLE '.$quote($column->table_name).' ALTER COLUMN company_id SET DEFAULT '.(int)$to);}
  DB::table('communication_companies')->where('id',$from)->delete();
 }
 foreach($names as $name){if(isset($keep[$name]))DB::table('communication_companies')->where('id',$keep[$name])->update(['name'=>$name,'updated_at'=>now()]);else $keep[$name]=DB::table('communication_companies')->insertGetId(['name'=>$name,'created_at'=>now(),'updated_at'=>now()]);}
 // Users remain intact; remove only their obsolete company assignments.
 DB::table('users')->whereIn('company_id',array_keys($remove))->update(['company_id'=>null]);
 // Invitations contain company IDs in JSON as well as their primary company column.
 foreach(DB::table('communication_invitations')->whereNotNull('company_ids')->get(['id','company_ids']) as $inv){$ids=json_decode($inv->company_ids,true)?:[];$ids=array_values(array_unique(array_filter(array_map(fn($id)=>$merge[$id]??$id,$ids),fn($id)=>!isset($remove[$id]))));DB::table('communication_invitations')->where('id',$inv->id)->update(['company_ids'=>json_encode($ids)]);}
 // Build the exact foreign-key closure of unwanted companies, including child demo records.
 foreach($tables as $t){$table=$t->tablename;DB::statement('CREATE TEMP TABLE '.$quote('cleanup_'.$table).' (row_tid tid PRIMARY KEY) ON COMMIT DROP');}
 $seed=function($table,$query)use($quote){return DB::affectingStatement('INSERT INTO '.$quote('cleanup_'.$table).' SELECT ctid FROM '.$quote($table).' WHERE '.$query.' ON CONFLICT DO NOTHING');};
 if($remove)$seed('communication_companies','id IN ('.implode(',',array_map('intval',array_keys($remove))).')');
 if($legacyPms->isNotEmpty())$seed('pms_companies','id IN ('.implode(',',$legacyPms->pluck('id')->all()).')');
 // Supplement legacy columns without foreign keys; PMS company_id uses a different ID namespace.
 if($remove)foreach($columns as $column)if($column->column_name==='company_id'&&$column->table_name!=='users'&&!str_starts_with($column->table_name,'pms_'))$seed($column->table_name,'company_id IN ('.implode(',',array_keys($remove)).')');
 do{$added=0;foreach($fks as $fk){if($fk->child==='users')continue;$cc=json_decode($fk->child_columns,true);$pc=json_decode($fk->parent_columns,true);$join=[];foreach($cc as $i=>$col)$join[]='c.'.$quote($col).'=p.'.$quote($pc[$i]);$added+=DB::affectingStatement('INSERT INTO '.$quote('cleanup_'.$fk->child).' SELECT c.ctid FROM '.$quote($fk->child).' c JOIN '.$quote($fk->parent).' p ON '.implode(' AND ',$join).' JOIN '.$quote('cleanup_'.$fk->parent).' d ON d.row_tid=p.ctid ON CONFLICT DO NOTHING');}}while($added);
 $deletions=[];foreach($tables as $t){$count=DB::table('cleanup_'.$t->tablename)->count();if($count)$deletions[$t->tablename]=$count;}
 // Finance immutability guards are suspended only within this transaction for explicitly removed TEST companies.
 // All normal application writes and foreign-key constraints remain protected.
 foreach(['accounting_journal_lines'=>'accounting_lines_guard','accounting_journals'=>'accounting_journal_guard','accounting_invoices'=>'accounting_invoice_guard','accounting_payments'=>'accounting_payment_guard'] as $table=>$trigger)DB::statement('ALTER TABLE '.$quote($table).' DISABLE TRIGGER '.$quote($trigger));
 // Clear the one nullable approval/evidence cycle before deleting child evidence.
 DB::statement('UPDATE accounting_approvals SET verified_copy_id=NULL WHERE ctid IN (SELECT row_tid FROM cleanup_accounting_approvals)');
 // ctid changes on update: refresh the selected approval rows from their company ownership.
 if($remove){DB::table('cleanup_accounting_approvals')->delete();$seed('accounting_approvals','company_id IN ('.implode(',',array_keys($remove)).')');}
 // RESTRICT foreign keys require children first; retry each statement in its own savepoint.
 $pending=array_keys($deletions);
 while($pending){$progress=false;$failures=[];foreach($pending as $i=>$table){try{DB::transaction(function()use($table,$quote){DB::delete('DELETE FROM '.$quote($table).' WHERE ctid IN (SELECT row_tid FROM '.$quote('cleanup_'.$table).')');});unset($pending[$i]);$progress=true;}catch(Illuminate\Database\QueryException $e){if($e->getCode()!=='23503')throw $e;$failures[$table]=$e->getPrevious()->getMessage();}}if(!$progress)throw new RuntimeException('Cleanup dependencies could not be resolved: '.json_encode($failures));}
 foreach(['accounting_journal_lines'=>'accounting_lines_guard','accounting_journals'=>'accounting_journal_guard','accounting_invoices'=>'accounting_invoice_guard','accounting_payments'=>'accounting_payment_guard'] as $table=>$trigger)DB::statement('ALTER TABLE '.$quote($table).' ENABLE TRIGGER '.$quote($trigger));
 DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
 foreach($fks as $fk)DB::statement('ALTER TABLE '.$quote($fk->child).' ALTER CONSTRAINT '.$quote($fk->conname).($fk->condeferrable?($fk->condeferred?' DEFERRABLE INITIALLY DEFERRED':' DEFERRABLE INITIALLY IMMEDIATE'):' NOT DEFERRABLE'));
 App\Modules\Pms\Services\PmsVisibility::syncCompanies();
 // Refresh announcement labels for retained renamed companies.
 foreach($keep as $name=>$id)DB::table('communication_conversations')->where('company_id',$id)->where('kind','community')->update(['name'=>$name.' announcements','updated_at'=>now()]);
 foreach($preservedCounts as $table=>$count)if(DB::table($table)->count()!==$count)throw new RuntimeException('Retained data count changed: '.$table);
 if(DB::table('accounting_journals')->whereNotIn('company_id',array_keys($remove))->orderBy('id')->get()->toJson()!==$retainedAccounting)throw new RuntimeException('Retained accounting journals changed.');
 $actual=DB::table('communication_companies')->pluck('name')->sort()->values()->all();$expected=$names;sort($expected);if($actual!==$expected)throw new RuntimeException('Final company directory does not match the approved list.');
 if($rehearse){DB::rollBack();echo 'Rehearsal passed; all changes rolled back. Snapshot: '.$backup.PHP_EOL;echo json_encode(['deleted_rows'=>$deletions,'companies'=>$actual],JSON_PRETTY_PRINT).PHP_EOL;exit;}
 DB::commit();
 file_put_contents($dir.'/cleanup-'.now()->format('Ymd-His').'.json',json_encode(['backup'=>basename($backup),'plan'=>$plan,'deleted_rows'=>$deletions,'final_companies'=>$actual],JSON_PRETTY_PRINT));
 echo 'Cleanup committed. Private data snapshot: '.$backup.PHP_EOL;echo json_encode(['deleted_rows'=>$deletions,'companies'=>$actual],JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){DB::rollBack();fwrite(STDERR,'Cleanup rolled back: '.$e->getMessage().PHP_EOL);exit(1);}
