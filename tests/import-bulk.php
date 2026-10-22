<?php
// Headerless CSV and multi-request imports, with actual WordPress accounts.
wp_set_current_user($admin);
$manual=['name'=>0,'email'=>1,'birth_date'=>2];
$plain=CCS::import_csv("\xEF\xBB\xBF\r\n\"Pessoa, Um\",no-header-one@example.invalid,07/03/1990\r\nPessoa Dois,no-header-two@example.invalid,1991-04-08\r\n",$manual);
check(count($plain)===2 && $plain[0]['status']==='ready' && $plain[0]['email']==='no-header-one@example.invalid','headerless CSV preserves the first person, BOM and quoted commas');
$single=CCS::import_csv("Pessoa Única;single-no-header@example.invalid;01011980",$manual);
check(count($single)===1 && $single[0]['status']==='ready','headerless CSV with one person works');
$bulk_csv='';
for ($i=1;$i<=201;$i++) { $bulk_csv.='Pessoa '.$i.';bulk-'.$i."@example.invalid;07/03/1990\n"; }
$bulk=CCS::import_prepare($bulk_csv);
check(count($bulk['source']['records'])===201 && !$bulk['source']['has_header'] && $bulk['source']['records'][0]['line']===1,'no header or fixed header names required for large file');
check(!array_filter($bulk['mapping'],fn($index)=>$index!==''),'no personal data used as header auto-mapping');
ob_start();CCS::import_page();$mapping_html=ob_get_clean();
check(str_contains($mapping_html,'Coluna 1 (Pessoa 1)') && str_contains($mapping_html,'name="has_header" value="1">'),'column positions and samples shown; header checkbox is unchecked by default');
$bulk=CCS::import_map($bulk['token'],$manual);
check(count(array_filter($bulk['rows'],fn($row)=>$row['status']==='ready'))===201,'all 201 people appear in mapped preview');
$bulk=CCS::import_commit($bulk['token'],true);
$created=count(array_filter($bulk['rows'],fn($row)=>$row['status']==='created'));
check(!$bulk['done'] && !empty($bulk['started']) && $created>0 && $created<=50,'large import saves progress within bounded requests');
import_rejects(fn()=>CCS::import_map($bulk['token'],$manual),409,'mapping cannot change after creating accounts');
ob_start();CCS::import_page();$progress_html=ob_get_clean();
check(str_contains($progress_html,'Continuar importação') && !str_contains($progress_html,'requestSubmit()'),'returning to progress allows explicit resume');
$_SERVER['REQUEST_METHOD']='POST';
$_POST=['import_action'=>'commit','token'=>$bulk['token'],'confirm'=>'1','_wpnonce'=>wp_create_nonce('ccs_import_users')];$_REQUEST=$_POST;
ob_start();CCS::import_page();$continue_html=ob_get_clean();
check(str_contains($continue_html,'requestSubmit()') && str_contains($continue_html,'Importando pessoas') && !str_contains($continue_html,'notice-error'),'confirmed import controller renders automatic continuation');
$_SERVER['REQUEST_METHOD']='GET';$_POST=[];$_REQUEST=[];
$bulk=get_transient('ccs_import_'.$admin);
$first_bulk=(int)email_exists('bulk-1@example.invalid');
wp_set_password('Preserve-while-resuming!',$first_bulk);
$requests=0;
while (!$bulk['done'] && $requests++<12) { $bulk=CCS::import_commit($bulk['token'],true); }
check($bulk['done'] && count(array_filter($bulk['rows'],fn($row)=>$row['status']==='created'))===201,'all 201 people created across automatic continuation requests');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_email LIKE 'bulk-%@example.invalid'")===201,'bulk continuation creates exactly one account per person');
$last_bulk=(int)email_exists('bulk-201@example.invalid');
check($first_bulk && $last_bulk && wp_check_password('07031990',get_userdata($last_bulk)->user_pass,$last_bulk),'first and last person imported with birthday password');
check(wp_check_password('Preserve-while-resuming!',get_userdata($first_bulk)->user_pass,$first_bulk),'continuing import preserves changed password of earlier processed person');
$_GET['import_page']=3;
ob_start();CCS::import_page();$last_page_html=ob_get_clean();
check(str_contains($last_page_html,'Página 3 de 3') && str_contains($last_page_html,'Pessoa 201') && !str_contains($last_page_html,'Pessoa 101'),'large result uses pagination without omitting last person');
unset($_GET['import_page']);
$bulk_replay=CCS::import_commit($bulk['token'],true);
check($bulk_replay['done'] && (int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_email LIKE 'bulk-%@example.invalid'")===201,'replaying completed bulk import creates no duplicates');
delete_transient('ccs_import_'.$admin);
