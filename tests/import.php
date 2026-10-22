<?php
// Runs inside wordpress-smoke.php, against the real WordPress users and MySQL.
require_once ABSPATH.'wp-admin/includes/template.php';
function import_rejects($callback,$code,$message) {
    try { $callback(); } catch (CCS_Exception $e) { check($e->getCode()===$code,$message);return; }
    check(false,$message.' (accepted unexpectedly)');
}
wp_set_current_user($admin);
$csv="\xEF\xBB\xBFContato;Aniversário;Pessoa da igreja;Celular pessoal;Acesso;Origem\r\n"
    ."imported@example.invalid;07/03/1990;Pessoa & <b>Exemplo</b>;(41) 99999-9999;voluntario;planilha\r\n"
    ."leader-import@example.invalid;2000-02-29;Líder Importado;;lider_de_equipe;planilha\r\n"
    ."imported@example.invalid;01011980;Repetido;;voluntario;planilha\r\n"
    ."bad-date@example.invalid;29/02/1991;Data Inválida;;voluntario;planilha\r\n"
    ."missing-date@example.invalid;;Sem Data;;voluntario;planilha\r\n"
    ."bad-role@example.invalid;01/01/1980;Administrador;;administrator;planilha\r\n"
    ."volunteer@example.invalid;;Conta Existente;;pastor;planilha\r\n";
$mapping=['name'=>'2','email'=>'0','birth_date'=>'1','phone'=>'3','role'=>'4'];
$before_import=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");
$existing=get_userdata($v);$existing_hash=$existing->user_pass;$existing_roles=$existing->roles;
$batch=CCS::import_prepare($csv,true);$upload_token=$batch['token'];
check($batch['stage']==='mapping' && !email_exists('imported@example.invalid'),'upload requires mapping without creating users');
import_rejects(fn()=>CCS::import_commit($upload_token,true),409,'cannot bypass mapping stage');
import_rejects(fn()=>CCS::import_map('wrong-token',$mapping),409,'mapping token required');
foreach ([
    array_merge($mapping,['birth_date'=>'']),
    array_merge($mapping,['email'=>'2']),
    array_merge($mapping,['email'=>'99']),
    array_merge($mapping,['email'=>'-1']),
    array_merge($mapping,['email'=>['0']]),
] as $index=>$invalid) { import_rejects(fn()=>CCS::import_map($upload_token,$invalid),400,'invalid mapping '.$index); }
$batch=CCS::import_map($upload_token,$mapping);$token=$batch['token'];
check($token!==$upload_token && array_column($batch['rows'],'status')===['ready','ready','skipped','invalid','invalid','invalid','skipped'],'mapped preview validates rows, roles, birthdays and duplicates');
check($batch['rows'][0]['name']==='Pessoa & Exemplo' && $batch['rows'][0]['phone']==='41999999999' && $batch['rows'][0]['birth_date']==='1990-03-07','arbitrary headers and unused column map correctly');
import_rejects(fn()=>CCS::import_commit($upload_token,true),409,'old mapping token cannot confirm a changed preview');
import_rejects(fn()=>CCS::import_commit($token,false),400,'explicit confirmation required');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users")===$before_import,'preview and rejected confirmations create no users');
$_SERVER['REQUEST_URI']='/wp-admin/admin.php?page=ccs-import';
$_SERVER['REQUEST_METHOD']='GET';
ob_start();CCS::import_page();$html=ob_get_clean();
check(str_contains($html,'name="mapping[birth_date]" required') && str_contains($html,'Conferir prévia') && str_contains($html,'name="confirm" required'),'admin page renders mapping, preview and confirmation');
check(str_contains($html,'&lt;b&gt;Exemplo&lt;/b&gt;') && !str_contains($html,'<b>Exemplo</b>'),'CSV samples are escaped in admin HTML');
foreach ([$p,$s,$d,$l,$v] as $denied) {
    wp_set_current_user($denied);
    import_rejects(fn()=>CCS::import_commit($token,true),403,'import denied to profile '.$denied);
}
$other_admin=wp_insert_user(['user_login'=>'other_import_admin','user_email'=>'other-admin@example.invalid','user_pass'=>wp_generate_password(32),'role'=>'administrator']);
wp_set_current_user($other_admin);
import_rejects(fn()=>CCS::import_commit($token,true),409,'preview belongs to the uploading administrator');
wp_set_current_user($admin);
$done=CCS::import_commit($token,true);
$new_id=(int)email_exists('imported@example.invalid');$leader_id=(int)email_exists('leader-import@example.invalid');
check($done['done'] && array_column($done['rows'],'status')===['created','created','skipped','invalid','invalid','invalid','skipped'],'only ready people are imported');
check($new_id && $leader_id && get_userdata($new_id)->roles===['ccs_volunteer'] && get_userdata($leader_id)->roles===['ccs_team'],'mapped roles applied to new accounts');
check(wp_check_password('07031990',get_userdata($new_id)->user_pass,$new_id),'birthday password has eight digits and preserves leading zeroes');
check(wp_check_password('29022000',get_userdata($leader_id)->user_pass,$leader_id),'ISO leap birthday creates ddmmaaaa password');
$login=wp_authenticate('imported@example.invalid','07031990');
check($login instanceof WP_User && $login->ID===$new_id,'imported person can log in using email and birthday');
check(get_user_meta($new_id,'ccs_birth_date',true)==='1990-03-07' && get_user_meta($new_id,'ccs_phone',true)==='41999999999','birth date and phone saved in user metadata');
check(get_userdata($v)->user_pass===$existing_hash && get_userdata($v)->roles===$existing_roles,'existing password and role are preserved even with missing birthday');
check(!email_exists('bad-date@example.invalid') && !email_exists('missing-date@example.invalid') && !email_exists('bad-role@example.invalid'),'invalid dates, missing dates and administrator role create no accounts');
check(!isset($done['source']) && !str_contains(wp_json_encode($done),'"password"'),'completed batch discards raw file and stores no password field');
$details=$wpdb->get_col("SELECT detail FROM ".CCS::table('audit')." WHERE action='user_imported'");
check(!str_contains(implode(' ',$details),'07031990'),'audit does not contain initial password');
wp_set_password('Changed-after-import!',$new_id);
$count_after=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");
$replayed=CCS::import_commit($token,true);
check($replayed['done'] && (int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users")===$count_after && wp_check_password('Changed-after-import!',get_userdata($new_id)->user_pass,$new_id),'resubmitting confirmation neither duplicates users nor resets password');
$repeat=CCS::import_prepare($csv,true);$repeat=CCS::import_map($repeat['token'],$mapping);
check($repeat['rows'][0]['status']==='skipped' && $repeat['rows'][1]['status']==='skipped','reimport skips existing accounts');
$default=CCS::import_csv("E-mail,Data de Nascimento,Nome Completo,Ignorar\nthird@example.invalid,7/3/1990,\"Nome, Sobrenome\",extra\n",null,true);
check($default[0]['status']==='ready' && $default[0]['role']==='ccs_volunteer' && $default[0]['name']==='Nome, Sobrenome','comma CSV auto-maps aliases and defaults to volunteer');
$duplicates=CCS::import_csv("Coluna;Coluna;Coluna\nPessoa;fourth@example.invalid;01011980\n",['name'=>0,'email'=>1,'birth_date'=>2],true);
check($duplicates[0]['status']==='ready','duplicate header titles supported through column indices');
foreach (['07/03/1990','1990-03-07','07031990','7/3/1990','07-03-1990','07.03.1990'] as $date) {
    check(CCS::import_birth_date($date)==='1990-03-07','supported birth date format '.$date);
}
foreach (['','31/04/1990','29/02/1991','07/03/90','03/07/1990 garbage',current_datetime()->modify('+1 day')->format('Y-m-d')] as $date) {
    check(CCS::import_birth_date($date)==='','invalid or future birth date rejected '.$date);
}
check(CCS::import_birth_date('29/02/2000')==='2000-02-29','leap birthday accepted');
import_rejects(fn()=>CCS::import_csv("nome;email;telefone\nPessoa;new@example.invalid;41999999999\n",null,true),400,'birth date mapping required');
import_rejects(fn()=>CCS::import_source(str_repeat('a',2*1024*1024+1)),400,'2 MB size limit');
check(count(CCS::import_source(str_repeat("Pessoa;x@example.invalid;01011990\n",1001))['records'])===1001,'CSV accepts more than 200 and 1000 people');
import_rejects(fn()=>CCS::import_source("nome;email;data_nascimento\n\xFF"),400,'non UTF-8 file rejected');
$uneven=CCS::import_csv("nome;email;data_nascimento\nPessoa;x@example.invalid;01011990;extra\n",null,true);
check($uneven[0]['status']==='invalid','mismatched CSV row rejected');
$bad_email=CCS::import_csv("nome;email;data_nascimento\nPessoa;bad;01011990\n",null,true);
check($bad_email[0]['status']==='invalid','invalid email rejected');
$late=CCS::import_prepare("nome;email;data_nascimento\nPessoa;late@example.invalid;01011990\n",true);
$late=CCS::import_map($late['token'],$late['mapping']);
$late_id=wp_insert_user(['user_login'=>'late_import','user_email'=>'late@example.invalid','user_pass'=>'Preserved-late-account!','role'=>'subscriber']);
$late=CCS::import_commit($late['token'],true);
check($late['rows'][0]['status']==='skipped' && wp_check_password('Preserved-late-account!',get_userdata($late_id)->user_pass,$late_id) && get_userdata($late_id)->roles===['subscriber'],'account created after preview is preserved at confirmation');
$pending=CCS::import_prepare("nome;email;data_nascimento\nPessoa;expired@example.invalid;01011990\n",true);
delete_transient('ccs_import_'.$admin);
import_rejects(fn()=>CCS::import_map($pending['token'],$pending['mapping']),409,'expired preview cannot be used');
set_transient('ccs_import_'.$admin,['token'=>'old-preview','rows'=>[],'done'=>false],HOUR_IN_SECONDS);
import_rejects(fn()=>CCS::import_commit('old-preview',true),409,'old plugin preview cannot create users with obsolete password rules');
delete_transient('ccs_import_'.$admin);
require __DIR__.'/import-bulk.php';
