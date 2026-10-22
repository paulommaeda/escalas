<?php
// Real WordPress/REST integration, loaded by wordpress-smoke.php.
function ev_user($name,$role='volunteer') {
    $id=wp_insert_user(['user_login'=>'event_'.$name,'display_name'=>$name,'user_email'=>'event_'.$name.'@example.invalid','user_pass'=>wp_generate_password(32),'role'=>'ccs_'.$role]);
    check(!is_wp_error($id),'event fixture user '.$name);return (int)$id;
}
function ev_publish($uid,$title,$deps,$days=70,$code=200) {
    $date=current_datetime()->modify('+'.$days.' days')->format('Y-m-d');
    return act($uid,['action'=>'publish_event','title'=>$title,'location'=>'Auditório','arrival'=>$date.'T17:30','starts'=>$date.'T18:00','ends'=>$date.'T20:00','requirements'=>$deps,'repeat'=>1,'interval'=>7,'notes'=>'Orientação para todos'],$code);
}
function ev_child($occ,$team) {
    global $wpdb;
    return (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.CCS::table('events')." WHERE occurrence_id=%d AND team_id=%d AND status='published' ORDER BY id DESC LIMIT 1",$occ,$team));
}
function ev_slots($event) { global $wpdb;return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.CCS::table('slots')." WHERE event_id=%d AND status<>'withdrawn' ORDER BY id",$event),ARRAY_A); }
function ev_edit($id,$extra=[]) {
    global $wpdb;$o=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.CCS::table('occurrences').' WHERE id=%d',$id),ARRAY_A);
    $a=array_intersect_key($o,array_flip(['title','location','arrival','starts','ends','notes']));
    foreach (['arrival','starts','ends'] as $key) { $a[$key]=substr(str_replace(' ','T',$a[$key]),0,16); }
    return array_merge($a,['action'=>'edit_occurrence','id'=>$id,'fingerprint'=>CCS::occurrence_fingerprint($id)],$extra);
}
$ev_leadA=ev_user('leadA','team');$ev_leadB=ev_user('leadB');$ev_leadC=ev_user('leadC','team');$ev_deptB=ev_user('deptB','department');
$ev_a=ev_user('baterista');$ev_b=ev_user('tecladista');$ev_c=ev_user('acolhimento');$ev_out=ev_user('substituto');
act($admin,['action'=>'function','name'=>'Teclado evento']);$ev_fn=latest('functions');
act($admin,['action'=>'department','name'=>'Eventos Artes','leader_id'=>$d]);$ev_depA=latest('departments');
act($admin,['action'=>'department','name'=>'Eventos Serviço','leader_id'=>$ev_deptB]);$ev_depB=latest('departments');
act($admin,['action'=>'department','name'=>'Eventos Direto','leader_id'=>$ev_deptB]);$ev_depD=latest('departments');
act($admin,['action'=>'team','name'=>'Evento Música 1','department_id'=>$ev_depA,'leader_id'=>$ev_leadA,'mode'=>'functions','members'=>[$ev_a,$ev_b],'functions'=>[['id'=>$fn,'quantity'=>1],['id'=>$ev_fn,'quantity'=>1]]]);$ev_teamA=latest('teams');
act($admin,['action'=>'team','name'=>'Evento Música 2','department_id'=>$ev_depA,'leader_id'=>$ev_leadB,'mode'=>'open','members'=>[$ev_b]]);$ev_teamB=latest('teams');
act($admin,['action'=>'team','name'=>'Evento Recepção','department_id'=>$ev_depB,'leader_id'=>$ev_leadC,'mode'=>'roster','members'=>[$ev_c]]);$ev_teamC=latest('teams');
act($ev_a,['action'=>'profile_functions','function_ids'=>[$fn]]);act($ev_b,['action'=>'profile_functions','function_ids'=>[$ev_fn]]);act($ev_out,['action'=>'profile_functions','function_ids'=>[$fn]]);
$ev_rules=[['department_id'=>$ev_depA,'team_count'=>1],['department_id'=>$ev_depB,'team_count'=>1]];
$ev_result=ev_publish($admin,'Evento unificado',$ev_rules);$ev_root=$ev_result['occurrence_ids'][0];
check(!ev_child($ev_root,$ev_teamA) && !ev_child($ev_root,$ev_teamC),'publish event without creating team schedules');
check((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.CCS::table('notifications')." WHERE occurrence_id=%d AND type='event_published' AND user_id IN (%d,%d,%d,%d,%d)",$ev_root,$d,$ev_deptB,$ev_leadA,$ev_leadB,$ev_leadC))===5,'only requested department leaders receive event publication');
check(!(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.CCS::table('notifications').' WHERE occurrence_id=%d AND user_id=%d',$ev_root,$ev_out)),'outsider not sent leadership publication notice');
ev_publish($admin,'Evento unificado',$ev_rules,70,409);
ev_publish($v,'Proibido',$ev_rules,71,403);ev_publish($s,'Proibido',$ev_rules,71,403);ev_publish($ev_leadA,'Proibido',$ev_rules,71,403);
ev_publish($d,'Outro departamento',$ev_rules,71,403);
ev_publish($admin,'Sem departamentos',[],71,400);
act($v,['action'=>'claim_event_team','id'=>$ev_root,'team_id'=>$ev_teamA],403);
act($ev_leadA,['action'=>'claim_event_team','id'=>$ev_root,'team_id'=>$ev_teamC],403);
act($s,['action'=>'claim_event_team','id'=>$ev_root,'team_id'=>$ev_teamC],403);
act($ev_leadA,['action'=>'claim_event_team','id'=>$ev_root,'team_id'=>$ev_teamA]);$ev_childA=ev_child($ev_root,$ev_teamA);$ev_slotsA=ev_slots($ev_childA);
check(count($ev_slotsA)===2 && $ev_slotsA[0]['status']==='available' && $ev_slotsA[1]['status']==='available','functions mode creates team function vacancies');
act($ev_leadA,['action'=>'claim_event_team','id'=>$ev_root,'team_id'=>$ev_teamA]);check(count(ev_slots($ev_childA))===2,'repeated team claim is idempotent');
act($ev_leadB,['action'=>'claim_event_team','id'=>$ev_root,'team_id'=>$ev_teamB],409);
act($ev_leadC,['action'=>'claim_event_team','id'=>$ev_root,'team_id'=>$ev_teamC]);$ev_childC=ev_child($ev_root,$ev_teamC);$ev_slotC=ev_slots($ev_childC)[0];
check($ev_slotC['status']==='pending' && (int)$ev_slotC['assigned_id']===$ev_c,'roster mode calls all active team members');
act($ev_c,['action'=>'respond','id'=>$ev_slotC['id'],'status'=>'confirmed']);
$ev_bat=array_values(array_filter($ev_slotsA,fn($r)=>(int)$r['function_id']===(int)$fn))[0]['id'];
$ev_key=array_values(array_filter($ev_slotsA,fn($r)=>(int)$r['function_id']===(int)$ev_fn))[0]['id'];
act($ev_c,['action'=>'claim','id'=>$ev_bat],403);act($ev_b,['action'=>'claim','id'=>$ev_bat],403);
act($ev_a,['action'=>'claim','id'=>$ev_bat]);act($ev_a,['action'=>'claim','id'=>$ev_bat],409);
act($ev_leadC,['action'=>'delegate','id'=>$ev_key,'to_id'=>$ev_b],403);
act($ev_leadA,['action'=>'delegate','id'=>$ev_key,'to_id'=>$ev_b]);
check(ev_slots($ev_childA)[1]['status']==='confirmed','delegated function is confirmed immediately');
act($ev_a,['action'=>'respond','id'=>$ev_slotC['id'],'status'=>'declined'],403);
act($ev_a,['action'=>'respond','id'=>$ev_bat,'status'=>'declined']);
act($ev_a,['action'=>'invite','id'=>$ev_bat,'to_id'=>$ev_out]);$ev_invite=latest('invites');
wp_set_current_user($ev_out);check(count(CCS::notification_feed()['requests'])===1,'cross-team substitute receives actionable bell request');
act($ev_out,['action'=>'answer_invite','id'=>$ev_invite,'accept'=>true]);
check((int)$wpdb->get_var($wpdb->prepare('SELECT assigned_id FROM '.CCS::table('slots').' WHERE id=%d',$ev_bat))===$ev_out,'cross-team qualified substitute joins the unified event');
wp_set_current_user($ev_a);$ev_state=CCS::state();$ev_o=array_values(array_filter($ev_state['occurrences'],fn($o)=>(int)$o['id']===(int)$ev_root))[0];
check(count($ev_o['requirements'])===2 && count($ev_o['requirements'][0]['events'])===1 && count($ev_o['requirements'][1]['events'])===1,'volunteer receives complete event across departments');
check(!$ev_o['can_manage'] && !$ev_o['can_edit'] && !$ev_o['requirements'][1]['can_assign'],'shared event grants no other-department editing permission');
check(!array_key_exists('email',$ev_state['users'][0]),'shared program excludes contact information');
$ev_foreignTeam=array_values(array_filter($ev_state['teams'],fn($t)=>(int)$t['id']===$ev_teamC))[0];
check(!$ev_foreignTeam['visible_to_me'] && !$ev_foreignTeam['members'] && !$ev_foreignTeam['functions'],'program visibility does not reveal other team membership directory');
check(in_array((int)$ev_invite,array_map('intval',array_column($ev_state['invites'],'id')),true),'requester still sees own replacement history');
act($ev_a,['action'=>'cancel_occurrence','id'=>$ev_root],403);act($d,['action'=>'cancel_occurrence','id'=>$ev_root],403);
act($ev_leadA,['action'=>'delete','type'=>'occurrence','id'=>$ev_root,'confirm'=>true],403);
$ev_editA=['action'=>'edit_schedule','id'=>$ev_childA,'title'=>'Evento unificado','location'=>'Auditório','arrival'=>substr(str_replace(' ','T',$ev_o['arrival']),0,16),'starts'=>substr(str_replace(' ','T',$ev_o['starts']),0,16),'ends'=>substr(str_replace(' ','T',$ev_o['ends']),0,16),'notes'=>'Chegar com instrumentos'];
act($ev_leadA,array_merge($ev_editA,['title'=>'Alteração global indevida']),403);
act($ev_leadC,$ev_editA,403);act($ev_leadA,$ev_editA);
check((string)$wpdb->get_var($wpdb->prepare('SELECT notes FROM '.CCS::table('events').' WHERE id=%d',$ev_childC))==='','team notes do not modify another department');
act($d,ev_edit($ev_root,['notes'=>'Não permitido']),403);
act($admin,ev_edit($ev_root,['requirements'=>[['department_id'=>$ev_depA,'team_count'=>1]]]),409);
$ev_shift=current_datetime()->modify('+70 days')->format('Y-m-d');
act($admin,ev_edit($ev_root,['arrival'=>$ev_shift.'T16:30','starts'=>$ev_shift.'T17:00','ends'=>$ev_shift.'T19:00']));
check($wpdb->get_var($wpdb->prepare('SELECT notes FROM '.CCS::table('events').' WHERE id=%d',$ev_childA))==='Chegar com instrumentos','global time edit preserves team-specific notes');
check($wpdb->get_var($wpdb->prepare('SELECT status FROM '.CCS::table('slots').' WHERE id=%d',$ev_bat))==='pending' && $wpdb->get_var($wpdb->prepare('SELECT status FROM '.CCS::table('slots').' WHERE id=%d',$ev_slotC['id']))==='pending','event time change asks all assigned volunteers to reconfirm');
act($ev_leadA,['action'=>'release_event_team','id'=>$ev_childC],403);
act($ev_leadA,['action'=>'release_event_team','id'=>$ev_childA]);
check(ev_child($ev_root,$ev_teamC)===$ev_childC && ev_slots($ev_childC)[0]['status']==='pending','releasing own team preserves another department and its slots');
act($ev_leadB,['action'=>'claim_event_team','id'=>$ev_root,'team_id'=>$ev_teamB]);$ev_childB=ev_child($ev_root,$ev_teamB);
check(count(ev_slots($ev_childB))===1 && ev_slots($ev_childB)[0]['status']==='available','appointed volunteer leader can assume open mode without management access');
wp_set_current_user($ev_leadB);check(!CCS::management_access(),'appointed volunteer leader still cannot see management');
act($ev_b,['action'=>'claim','id'=>ev_slots($ev_childB)[0]['id']]);
// Capacity and optimistic department edits preserve teams left in the selection.
act($admin,ev_edit($ev_root,['requirements'=>[['department_id'=>$ev_depA,'team_count'=>2],['department_id'=>$ev_depB,'team_count'=>1]]]));
act($ev_leadA,['action'=>'claim_event_team','id'=>$ev_root,'team_id'=>$ev_teamA]);$ev_newA=ev_child($ev_root,$ev_teamA);
act($admin,ev_edit($ev_root,['requirements'=>$ev_rules]),409);
wp_set_current_user($d);$ev_o=array_values(array_filter(CCS::state()['occurrences'],fn($o)=>(int)$o['id']===(int)$ev_root))[0];$ev_r=array_values(array_filter($ev_o['requirements'],fn($r)=>(int)$r['department_id']===$ev_depA))[0];
act($d,['action'=>'assign_occurrence','id'=>$ev_root,'department_id'=>$ev_depA,'team_ids'=>[$ev_teamB],'fingerprint'=>$ev_r['fingerprint']]);
check(ev_child($ev_root,$ev_teamB)===$ev_childB && ev_slots($ev_childB)[0]['status']==='confirmed','department selection preserves unchanged participation and confirmation');
act($d,['action'=>'assign_occurrence','id'=>$ev_root,'department_id'=>$ev_depA,'team_ids'=>[$ev_teamA],'fingerprint'=>$ev_r['fingerprint']],409);
check(ev_child($ev_root,$ev_teamC)===$ev_childC,'department edit cannot alter another department');
// Department without teams can participate directly.
$ev_direct=ev_publish($admin,'Participação direta',[['department_id'=>$ev_depD,'team_count'=>1]],80)['occurrence_ids'][0];
act($d,['action'=>'claim_event_department','id'=>$ev_direct,'department_id'=>$ev_depD],403);
act($ev_deptB,['action'=>'claim_event_department','id'=>$ev_direct,'department_id'=>$ev_depD]);$ev_implicit=latest('teams');
check((int)CCS::team($ev_implicit)['is_department']===1 && count(ev_slots(ev_child($ev_direct,$ev_implicit)))===1,'department without teams receives direct open participation');
act($d,['action'=>'claim_event_department','id'=>$ev_root,'department_id'=>$ev_depA],409);
// Single-department leader can publish/edit only their own event.
$ev_own=ev_publish($d,'Evento do departamento',[['department_id'=>$ev_depA,'team_count'=>1]],90)['occurrence_ids'][0];
act($d,ev_edit($ev_own,['notes'=>'Orientação do líder']));act($ev_deptB,ev_edit($ev_own),403);
// Transaction rollback never leaks notices or partial repeated dates.
$ev_pre=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('notifications'));
$ev_occPre=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('occurrences'));
$ev_duplicate=ev_publish($admin,'Bloqueio de lote',[['department_id'=>$ev_depA,'team_count'=>1]],107)['occurrence_ids'][0];
$ev_pre=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('notifications'));$ev_occPre=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('occurrences'));
$ev_dt=current_datetime()->modify('+100 days')->format('Y-m-d');
act($admin,['action'=>'publish_event','title'=>'Bloqueio de lote','location'=>'Auditório','arrival'=>$ev_dt.'T17:30','starts'=>$ev_dt.'T18:00','ends'=>$ev_dt.'T20:00','requirements'=>[['department_id'=>$ev_depA,'team_count'=>1]],'repeat'=>2,'interval'=>7],409);
check((int)$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('notifications'))===$ev_pre && (int)$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('occurrences'))===$ev_occPre,'failed repeated publication rolls back all event rows and notifications');
// Notifications are private; secretaria can only acknowledge their own.
wp_set_current_user($admin);CCS::notify_people([$s],'test_notice','Consulta','Aviso de teste',$ev_root,0,'secretary-read');
$ev_notice=latest('notifications');act($ev_a,['action'=>'notice_read','ids'=>[$ev_notice]],403);act($s,['action'=>'notice_read','ids'=>[$ev_notice]]);
check((bool)$wpdb->get_var($wpdb->prepare('SELECT read_at FROM '.CCS::table('notifications').' WHERE id=%d',$ev_notice)),'secretary can mark personal notification read');
act($s,['action'=>'claim','id'=>ev_slots($ev_newA)[0]['id']],403);act($s,['action'=>'release_event_team','id'=>$ev_childC],403);
wp_set_current_user($ev_out);$ev_feed=CCS::notification_feed();
check(!in_array((int)$ev_notice,array_map('intval',array_column($ev_feed['items'],'id')),true),'feed contains only own notification records');
check(!CCS::push_available() && $wpdb->get_var($wpdb->prepare('SELECT push_status FROM '.CCS::table('notifications').' WHERE id=%d',$ev_notice))==='pending','internal inbox works without optional push plugin');
// Authentication retains the same-origin event link with password or passkey.
$_GET['ccs_event']=(string)$ev_root;$_GET['redirect_to']='https://example.invalid/';
check(CCS::auth_destination()===add_query_arg('ccs_event',$ev_root,CCS::url()),'authentication preserves event deep link without accepting external redirect');unset($_GET['ccs_event'],$_GET['redirect_to']);
// Legacy migration groups schedules without replacing slot/invitation IDs.
$ev_legacyData=['title'=>'Legado conjunto','location'=>'Auditório','arrival'=>$ev_dt.' 09:00:00','starts'=>$ev_dt.' 10:00:00','ends'=>$ev_dt.' 12:00:00','status'=>'published','created_by'=>$admin,'notes'=>'Orientações antigas'];
$ev_oldA=CCS::insert('events',array_merge($ev_legacyData,['team_id'=>$ev_teamA,'mode'=>'functions','occurrence_id'=>0]));
$ev_oldC=CCS::insert('events',array_merge($ev_legacyData,['team_id'=>$ev_teamC,'mode'=>'roster','occurrence_id'=>0]));
$ev_oldSlot=CCS::insert('slots',['event_id'=>$ev_oldA,'function_id'=>$fn,'function_name'=>'Bateria','original_id'=>$ev_a,'assigned_id'=>$ev_out,'status'=>'confirmed']);
$ev_oldInv=CCS::insert('invites',['slot_id'=>$ev_oldSlot,'from_id'=>$ev_a,'to_id'=>$ev_out,'status'=>'accepted','created_at'=>current_time('mysql',true)]);
delete_option('ccs_unified_events');CCS::migrate_event_groups();
$ev_oldRoot=(int)$wpdb->get_var($wpdb->prepare('SELECT occurrence_id FROM '.CCS::table('events').' WHERE id=%d',$ev_oldA));
check($ev_oldRoot>0 && ev_child($ev_oldRoot,$ev_teamC)===$ev_oldC,'legacy team schedules become one event');
check((int)$wpdb->get_var($wpdb->prepare('SELECT assigned_id FROM '.CCS::table('slots').' WHERE id=%d',$ev_oldSlot))===$ev_out && (int)$wpdb->get_var($wpdb->prepare('SELECT slot_id FROM '.CCS::table('invites').' WHERE id=%d',$ev_oldInv))===$ev_oldSlot,'migration preserves assignment, confirmation, and accepted replacement identity');
$ev_occPre=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('occurrences'));CCS::migrate_event_groups();check((int)$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('occurrences'))===$ev_occPre,'event migration is idempotent');
act($admin,['action'=>'cancel_occurrence','id'=>$ev_root]);act($ev_leadA,['action'=>'claim_event_team','id'=>$ev_root,'team_id'=>$ev_teamA],409);
act($ev_c,['action'=>'respond','id'=>$ev_slotC['id'],'status'=>'confirmed'],409);
act($admin,['action'=>'delete','type'=>'occurrence','id'=>$ev_root,'confirm'=>true]);
wp_set_current_user($ev_a);check(!in_array((int)$ev_root,array_map('intval',array_column(CCS::state()['occurrences'],'id')),true),'deleted event disappears from all volunteers agenda');

// Recurring templates retain automatic modes while new claim policy waits for leaders.
act($admin,['action'=>'celebration','name'=>'Modelo de evento','location'=>'Templo','starts_time'=>'18:00','ends_time'=>'20:00','arrival_minutes'=>30,'rules'=>[['department_id'=>$ev_depA,'required'=>true,'policy'=>'claim','team_count'=>1,'team_ids'=>[]],['department_id'=>$ev_depB,'required'=>true,'policy'=>'rotation','team_ids'=>[$ev_teamC]]]]);$ev_tpl=latest('celebrations');
$ev_date=current_datetime()->modify('+130 days')->format('Y-m-d');
$ev_preview=act($admin,['action'=>'celebration_preview','id'=>$ev_tpl,'date'=>$ev_date,'repeat'=>2,'interval'=>7]);
check(!$ev_preview['preview'][0]['rules'][0]['selected_team_ids'] && (int)$ev_preview['preview'][0]['rules'][1]['selected_team_ids'][0]===$ev_teamC,'claim template waits for leader and rotation remains automatic');
act($admin,['action'=>'create_occurrences','id'=>$ev_tpl,'date'=>$ev_date,'repeat'=>2,'interval'=>7,'revision'=>$ev_preview['revision']]);
$ev_tplRoot=latest('occurrences');check(ev_child($ev_tplRoot,$ev_teamC)>0 && !ev_child($ev_tplRoot,$ev_teamA),'one recurring event includes preselected teams and unfilled department requirements');
act($ev_leadA,['action'=>'claim_event_team','id'=>$ev_tplRoot,'team_id'=>$ev_teamA]);$ev_tplChild=ev_child($ev_tplRoot,$ev_teamA);
act($admin,ev_edit($ev_tplRoot,['requirements'=>[['department_id'=>$ev_depA,'team_count'=>1],['department_id'=>$ev_depB,'team_count'=>1]]]));
$ev_tplRules=json_decode($wpdb->get_var($wpdb->prepare('SELECT rules_snapshot FROM '.CCS::table('occurrences').' WHERE id=%d',$ev_tplRoot)),true);
check($ev_tplRules[1]['policy']==='rotation','editing event details preserves existing rotation policy');
act($admin,['action'=>'celebration','name'=>'Modelo fixo','location'=>'Templo','starts_time'=>'18:00','ends_time'=>'20:00','arrival_minutes'=>30,'rules'=>[['department_id'=>$ev_depA,'required'=>true,'policy'=>'fixed','team_ids'=>[$ev_teamA]]]]);$ev_fixed=latest('celebrations');
$ev_date=current_datetime()->modify('+150 days')->format('Y-m-d');
$ev_preview=act($admin,['action'=>'celebration_preview','id'=>$ev_fixed,'date'=>$ev_date]);
act($admin,['action'=>'create_occurrences','id'=>$ev_fixed,'date'=>$ev_date,'revision'=>$ev_preview['revision']]);$ev_fixedRoot=latest('occurrences');
act($ev_leadA,['action'=>'release_event_team','id'=>ev_child($ev_fixedRoot,$ev_teamA)]);
act($ev_leadB,['action'=>'claim_event_team','id'=>$ev_fixedRoot,'team_id'=>$ev_teamB],403);
act($ev_leadA,['action'=>'claim_event_team','id'=>$ev_fixedRoot,'team_id'=>$ev_teamA]);

wp_set_current_user($admin);CCS::notify_people([$ev_out],'test_notice','Aviso','Ainda não lido',0,0,'all-read-outsider');$ev_allOwn=latest('notifications');
CCS::notify_people([$v],'test_notice','Aviso','Outra pessoa',0,0,'all-read-foreign');$ev_allForeign=latest('notifications');
act($ev_out,['action'=>'notice_read','all'=>true]);
check((bool)$wpdb->get_var($wpdb->prepare('SELECT read_at FROM '.CCS::table('notifications').' WHERE id=%d',$ev_allOwn)) && !$wpdb->get_var($wpdb->prepare('SELECT read_at FROM '.CCS::table('notifications').' WHERE id=%d',$ev_allForeign)),'mark all read updates only current user notices');
