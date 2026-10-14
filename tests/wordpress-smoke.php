<?php
require getenv('CCS_WP_PATH').'/wp-load.php';
add_filter('pre_wp_mail',fn()=>true);
$checks=0;
function check($value,$message) { global $checks;if (!$value) { throw new Exception('FAIL '.$message); }$checks++;echo "PASS $message\n"; }
function act($uid,$data,$code=200) {
    wp_set_current_user($uid);$r=new WP_REST_Request('POST','/ccshalom/v1/action');$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($data));
    $res=rest_get_server()->dispatch($r);check($res->get_status()===$code,$data['action'].' '.$res->get_status().' '.wp_json_encode($res->get_data()));return $res->get_data();
}
function latest($name) { global $wpdb;return (int)$wpdb->get_var('SELECT MAX(id) FROM '.CCS::table($name)); }
check(get_option('ccs_db_version')===CCS_VERSION,'schema and version');
foreach (['departments','teams','functions','team_functions','qualifications','events','slots','invites','celebrations','occurrences','audit','passkeys'] as $t) { check((bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like(CCS::table($t)))),'table '.$t); }
$admin=get_user_by('login','admin')->ID;
$users=[];foreach (['department','team','volunteer','secretary','pastor'] as $role) { $users[$role]=wp_insert_user(['user_login'=>'test_'.$role,'user_email'=>$role.'@example.invalid','user_pass'=>wp_generate_password(32),'role'=>'ccs_'.$role]);check(!is_wp_error($users[$role]),'create '.$role); }
$d=$users['department'];$l=$users['team'];$v=$users['volunteer'];$s=$users['secretary'];$p=$users['pastor'];
foreach ([$admin,$d,$p,$s] as $u) { wp_set_current_user($u);check(CCS::management_access(),'management allowed '.$u); }
foreach ([$l,$v] as $u) { wp_set_current_user($u);check(!CCS::management_access(),'management denied '.$u); }
check(CCS::participant($s) && !CCS::can_serve($s),'secretary login without participation');
act($s,['action'=>'function','name'=>'Forbidden'],403);
act($v,['action'=>'function','name'=>'Forbidden'],403);
act($admin,['action'=>'function','name'=>'Bateria']);$fn=latest('functions');
act($d,['action'=>'person_functions','id'=>$v,'function_ids'=>[$fn]]);
act($v,['action'=>'person_functions','id'=>$l,'function_ids'=>[$fn]],403);
act($admin,['action'=>'department','name'=>'Artes','leader_id'=>$d]);$dep=latest('departments');
act($d,['action'=>'team','name'=>'Música 1','department_id'=>$dep,'leader_id'=>$l,'members'=>[$v],'functions'=>[['id'=>$fn,'quantity'=>1]]]);$team=latest('teams');
act($admin,['action'=>'team','name'=>'Invalid','department_id'=>$dep,'members'=>[$s]],400);
$date=current_datetime()->modify('+20 days');
$event=['title'=>'Celebração','location'=>'Igreja','arrival'=>$date->format('Y-m-d').'T18:30','starts'=>$date->format('Y-m-d').'T19:00','ends'=>$date->format('Y-m-d').'T21:00','mode'=>'functions','repeat'=>1,'interval'=>7,'team_ids'=>[$team]];
act($d,array_merge($event,['action'=>'schedule']));$slot=latest('slots');
act($l,['action'=>'delegate','id'=>$slot,'to_id'=>$v]);
check($wpdb->get_var('SELECT status FROM '.CCS::table('slots')." WHERE id=$slot")==='confirmed','delegation confirmed');
act($v,['action'=>'respond','id'=>$slot,'status'=>'declined']);
act($v,['action'=>'invite','id'=>$slot,'to_id'=>$s],400);
act($p,['action'=>'profile_functions','function_ids'=>[$fn]]);
act($v,['action'=>'invite','id'=>$slot,'to_id'=>$p]);$invite=latest('invites');
wp_set_current_user($p);check(count(CCS::notifications())===1,'recipient gets bell request');
act($d,['action'=>'answer_invite','id'=>$invite,'accept'=>true],403);
act($p,['action'=>'answer_invite','id'=>$invite,'accept'=>true]);
check($wpdb->get_var('SELECT assigned_id FROM '.CCS::table('slots')." WHERE id=$slot")==$p,'cross-team replacement');
wp_set_current_user($s);$state=CCS::state();check(count($state['teams'])===1 && !$state['events'][0]['can_claim'],'secretary sees without claiming');
wp_set_current_user($admin);check(CCS::import_allowed(),'admin import');
wp_set_current_user($p);check(!CCS::import_allowed(),'pastor not admin cannot import');
$rows=CCS::import_csv("\xEF\xBB\xBFnome;email;telefone;perfil\r\nJoão;novo@example.invalid;41999999999;voluntario\r\nDuplicado;novo@example.invalid;;\r\nInvalid;bad;;\r\nElevated;other@example.invalid;;administrator\r\n");
check(array_column($rows,'status')===['ready','skipped','invalid','invalid'],'CSV validation and duplicates');
check(CCS::import_csv("nome,email\nTeste,third@example.invalid\n")[0]['status']==='ready','comma CSV');
$mock=['tag_name'=>'v9.9.9','draft'=>false,'prerelease'=>false,'body'=>'Release','assets'=>[['name'=>'ccshalom-escalas.zip','browser_download_url'=>'https://github.com/paulommaeda/escalas/releases/download/v9.9.9/ccshalom-escalas.zip']]];
add_filter('pre_http_request',function($pre,$args,$url) use (&$mock) { if (str_starts_with($url,'https://api.github.com/repos/')) { return ['headers'=>[],'response'=>['code'=>200],'body'=>wp_json_encode($mock),'cookies'=>[]]; }return $pre; },10,3);
wp_set_current_user($admin);
check(ccs_github_repository()==='paulommaeda/escalas','default repository');
$r=ccs_github_check(true);check($r['ok'] && $r['version']==='9.9.9','stable release asset');
$info=apply_filters('update_plugins_github.com',false,[],plugin_basename(CCS_FILE),[]);
check($info['version']==='9.9.9' && str_ends_with($info['package'],'/ccshalom-escalas.zip'),'native update hook');
wp_clean_plugins_cache(true);wp_update_plugins();
$updates=get_site_transient('update_plugins');
check(isset($updates->response[plugin_basename(CCS_FILE)]) && $updates->response[plugin_basename(CCS_FILE)]->new_version==='9.9.9','WordPress offers GitHub update');
check(apply_filters('update_plugins_github.com','unchanged',[],'other/other.php',[])==='unchanged','other plugins untouched');
$mock['assets'][0]['browser_download_url']='https://example.invalid/ccshalom-escalas.zip';check(!ccs_github_check(true)['ok'],'foreign download rejected');
$mock['prerelease']=true;check(!ccs_github_check(true)['ok'],'prerelease rejected');
update_option('ccs_github_enabled','0');check(ccs_release()===false,'updates disabled');
$mock['prerelease']=false;$mock['assets'][0]['browser_download_url']='https://github.com/paulommaeda/escalas/releases/download/v9.9.9/ccshalom-escalas.zip';
update_option('ccs_github_enabled','1');ccs_github_check(true);
$before=$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('events'));CCS::activate();check($before===$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('events')),'idempotent schema');
echo "TOTAL $checks WordPress checks\n";
