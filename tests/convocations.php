<?php
// Leadership-only functional appointments, independent from a fixed pastoral role/name.
function cv_delegate($uid,$slot,$to,$code=200,$extra=[]) {
    return act($uid,array_merge(['action'=>'delegate','id'=>$slot,'to_id'=>$to,'fingerprint'=>CCS::convocation_fingerprint($slot)],$extra),$code);
}
function cv_slot($id) { global $wpdb;return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.CCS::table('slots').' WHERE id=%d',$id),ARRAY_A); }
$cv_one=ev_user('pastor_designado','pastor');$cv_two=ev_user('pastor_integrante');$cv_three=ev_user('pastor_outro');$cv_out=ev_user('pastor_externo');
act($admin,['action'=>'function','name'=>'Pregação']);$cv_sermon=latest('functions');
act($d,['action'=>'function','name'=>'Direção do culto']);$cv_direction=latest('functions');
act($admin,['action'=>'department','name'=>'Pastoral','leader_id'=>$d]);$cv_dep=latest('departments');
act($admin,['action'=>'team','name'=>'Equipe pastoral','department_id'=>$cv_dep,'leader_id'=>$ev_leadA,'mode'=>'assigned','members'=>[$cv_one,$cv_two,$cv_three],'functions'=>[['id'=>$cv_sermon,'quantity'=>1],['id'=>$cv_direction,'quantity'=>1]]]);$cv_team=latest('teams');
act($admin,['action'=>'person_functions','id'=>$cv_one,'function_ids'=>[$cv_sermon]]);
act($d,['action'=>'person_functions','id'=>$cv_two,'function_ids'=>[$cv_sermon,$cv_direction]]);
act($cv_out,['action'=>'profile_functions','function_ids'=>[$cv_sermon]]);
$cv_root=ev_publish($admin,'Celebração pastoral',[['department_id'=>$cv_dep,'team_count'=>1]],300)['occurrence_ids'][0];
act($ev_leadA,['action'=>'claim_event_team','id'=>$cv_root,'team_id'=>$cv_team]);$cv_event=ev_child($cv_root,$cv_team);$cv_rows=ev_slots($cv_event);
check(count($cv_rows)===2 && count(array_filter($cv_rows,fn($s)=>$s['status']==='available'&&!(int)$s['assigned_id']))===2,'assigned mode creates function needs without summoning every team member');
$cv_preach=array_values(array_filter($cv_rows,fn($r)=>(int)$r['function_id']===$cv_sermon))[0]['id'];$cv_direct=array_values(array_filter($cv_rows,fn($r)=>(int)$r['function_id']===$cv_direction))[0]['id'];
check((int)cv_slot($cv_preach)['function_id']===$cv_sermon && (int)cv_slot($cv_direct)['function_id']===$cv_direction,'pastoral functions use general catalogue ids without fixed function names');
check(!(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.CCS::table('notifications')." WHERE occurrence_id=%d AND user_id IN (%d,%d,%d) AND type='team_published'",$cv_root,$cv_one,$cv_two,$cv_three)),'unassigned members receive no open-vacancy or premature personal call notice');
foreach ([$cv_one,$cv_two,$admin,$ev_leadA,$s] as $uid) {
    [$cv_state]=ev_visible_occurrence($uid,$cv_root);
    $cv_visible=array_values(array_filter($cv_state['events'],fn($e)=>(int)$e['id']===$cv_event))[0];
    $cv_permissions=array_column($cv_state['slots'],'can_claim','id');
    check(!$cv_visible['can_claim'] && !$cv_permissions[$cv_preach] && !$cv_permissions[$cv_direct],'assigned needs never offer personal claim, including pastor/admin '.$uid);
    act($uid,['action'=>'claim','id'=>$cv_preach,'mode'=>'functions'],403);
}
[$cv_state]=ev_visible_occurrence($ev_leadA,$cv_root);$cv_visible=array_values(array_filter($cv_state['events'],fn($e)=>(int)$e['id']===$cv_event))[0];
check($cv_visible['can_delegate'] && !CCS::management_access(),'appointed team leader delegates assigned functions without gaining management tab');
$cv_visibleSlots=array_column($cv_state['slots'],null,'id');check(($cv_visibleSlots[$cv_preach]['convocation_fingerprint']??'')===CCS::convocation_fingerprint($cv_preach),'leader receives concurrency token matching the displayed appointment snapshot');
cv_delegate($ev_leadC,$cv_preach,$cv_one,403);cv_delegate($cv_two,$cv_preach,$cv_two,403);cv_delegate($s,$cv_preach,$cv_one,403);
act($ev_leadA,['action'=>'delegate','id'=>$cv_preach,'to_id'=>$cv_one],409);
cv_delegate($ev_leadA,$cv_preach,$cv_one,409,['fingerprint'=>'outdated']);
cv_delegate($ev_leadA,$cv_preach,$cv_three,400);cv_delegate($ev_leadA,$cv_preach,$cv_out,400);cv_delegate($ev_leadA,$cv_preach,$s,400);
update_user_meta($cv_one,'ccs_inactive','1');cv_delegate($ev_leadA,$cv_preach,$cv_one,400);delete_user_meta($cv_one,'ccs_inactive');
$cv_stale=CCS::convocation_fingerprint($cv_preach);cv_delegate($ev_leadA,$cv_preach,$cv_one);
check(cv_slot($cv_preach)['status']==='confirmed' && (int)cv_slot($cv_preach)['assigned_id']===$cv_one,'leadership appointment is confirmed immediately');
wp_set_current_user($cv_one);$cv_feed=CCS::notification_feed();check((bool)array_filter($cv_feed['items'],fn($n)=>$n['type']==='assigned'&&(int)$n['event_id']===$cv_event&&str_contains($n['body'],'Pregação')),'appointed pastor receives individual notice with function');
$cv_noticeCount=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('notifications'));
cv_delegate($ev_leadA,$cv_preach,$cv_one);check((int)$wpdb->get_var('SELECT COUNT(*) FROM '.CCS::table('notifications'))===$cv_noticeCount,'saving the same confirmed appointment does not send duplicate notices');
cv_delegate($ev_leadA,$cv_preach,$cv_two,409,['fingerprint'=>$cv_stale]);
act($cv_one,['action'=>'release_slot','id'=>$cv_preach],403);
act($cv_two,['action'=>'respond','id'=>$cv_preach,'status'=>'declined'],403);
act($cv_one,['action'=>'respond','id'=>$cv_preach,'status'=>'declined']);
$cv_beforeInvite=CCS::convocation_fingerprint($cv_preach);
act($cv_one,['action'=>'invite','id'=>$cv_preach,'to_id'=>$cv_out]);$cv_invite=latest('invites');
cv_delegate($ev_leadA,$cv_preach,$cv_two,409,['fingerprint'=>$cv_beforeInvite]);
cv_delegate($ev_leadA,$cv_preach,$cv_two);
check((int)cv_slot($cv_preach)['assigned_id']===$cv_two && $wpdb->get_var($wpdb->prepare('SELECT status FROM '.CCS::table('invites').' WHERE id=%d',$cv_invite))==='cancelled','leader can reassign declined call and cancel pending replacement requests');
act($cv_out,['action'=>'answer_invite','id'=>$cv_invite,'accept'=>true],409);
wp_set_current_user($cv_one);check((bool)array_filter(CCS::notification_feed()['items'],fn($n)=>$n['type']==='convocation_changed'&&(int)$n['event_id']===$cv_event),'previously appointed person is informed of reassignment');
act($cv_two,['action'=>'respond','id'=>$cv_preach,'status'=>'declined']);
act($cv_two,['action'=>'invite','id'=>$cv_preach,'to_id'=>$cv_one]);$cv_invite=latest('invites');
act($cv_one,['action'=>'answer_invite','id'=>$cv_invite,'accept'=>true]);
check((int)cv_slot($cv_preach)['assigned_id']===$cv_one && cv_slot($cv_preach)['status']==='confirmed','consensual replacement still requires same global function');
$cv_shift=current_datetime()->modify('+300 days')->format('Y-m-d');$cv_prior=CCS::convocation_fingerprint($cv_preach);
act($admin,ev_edit($cv_root,['arrival'=>$cv_shift.'T16:30','starts'=>$cv_shift.'T17:00','ends'=>$cv_shift.'T19:00']));
check((int)cv_slot($cv_preach)['assigned_id']===$cv_one && (int)cv_slot($cv_preach)['original_id']===$cv_two && cv_slot($cv_preach)['status']==='pending','event time change keeps current substitute and asks for confirmation');
cv_delegate($ev_leadA,$cv_preach,$cv_two,409,['fingerprint'=>$cv_prior]);
act($cv_one,['action'=>'respond','id'=>$cv_preach,'status'=>'confirmed']);
// Another function is created later and added to this team without source changes.
act($d,['action'=>'function','name'=>'Acolhimento pastoral especial']);$cv_custom=latest('functions');
act($ev_leadA,['action'=>'team_functions','id'=>$cv_team,'functions'=>[['id'=>$cv_sermon,'quantity'=>1],['id'=>$cv_direction,'quantity'=>1],['id'=>$cv_custom,'quantity'=>1]]]);
act($ev_leadA,['action'=>'vacancies','id'=>$cv_event,'function_id'=>$cv_custom,'quantity'=>1]);$cv_newSlot=latest('slots');
act($admin,['action'=>'person_functions','id'=>$cv_three,'function_ids'=>[$cv_custom]]);
cv_delegate($ev_leadA,$cv_newSlot,$cv_three);
check(cv_slot($cv_newSlot)['function_name']==='Acolhimento pastoral especial','new custom function can be added and assigned after event publication');
cv_delegate($ev_leadA,$cv_direct,$cv_three,400);
// Multiple functions within the same event can be assigned to the same person.
cv_delegate($ev_leadA,$cv_direct,$cv_two);
cv_delegate($ev_leadA,$cv_preach,$cv_two);
check((int)cv_slot($cv_preach)['assigned_id']===$cv_two && (int)cv_slot($cv_direct)['assigned_id']===$cv_two,'same person can be appointed to two functions in one event');
act($ev_leadA,['action'=>'vacancies','id'=>$cv_event,'function_id'=>$cv_direction,'quantity'=>0],409);
$cv_clear=['action'=>'clear_convocation','id'=>$cv_direct,'fingerprint'=>CCS::convocation_fingerprint($cv_direct),'confirm'=>true];
act($cv_two,$cv_clear,403);act($s,$cv_clear,403);act($ev_leadA,array_merge($cv_clear,['confirm'=>false]),400);act($ev_leadA,array_merge($cv_clear,['fingerprint'=>'old']),409);
act($ev_leadA,$cv_clear);check(cv_slot($cv_direct)['status']==='available' && !(int)cv_slot($cv_direct)['assigned_id'],'leader can explicitly withdraw a call without opening volunteering');
act($cv_two,['action'=>'claim','id'=>$cv_direct],403);
act($ev_leadA,['action'=>'vacancies','id'=>$cv_event,'function_id'=>$cv_direction,'quantity'=>0]);check(cv_slot($cv_direct)['status']==='withdrawn','leader can remove needs after withdrawing occupied call');
act($admin,['action'=>'user','id'=>$cv_three,'name'=>'pastor_outro','email'=>get_userdata($cv_three)->user_email,'role'=>'ccs_volunteer','active'=>false]);
check(cv_slot($cv_newSlot)['status']==='available' && !(int)cv_slot($cv_newSlot)['assigned_id'],'deactivation reopens a directed need only for leadership assignment');
wp_set_current_user($ev_leadA);check((bool)array_filter(CCS::notification_feed()['items'],fn($n)=>$n['type']==='convocation_pending'&&(int)$n['event_id']===$cv_event),'leader is notified when an assigned person is deactivated');
// Snapshot per date: changing a team default never silently changes published participation.
act($admin,['action'=>'team','id'=>$cv_team,'name'=>'Equipe pastoral','department_id'=>$cv_dep,'leader_id'=>$ev_leadA,'mode'=>'functions','members'=>[$cv_one,$cv_two],'functions'=>[['id'=>$cv_sermon,'quantity'=>1],['id'=>$cv_direction,'quantity'=>1]]]);
check($wpdb->get_var($wpdb->prepare('SELECT mode FROM '.CCS::table('events').' WHERE id=%d',$cv_event))==='assigned','changing default to volunteering preserves existing directed appointment mode');
$cv_newRoot=ev_publish($admin,'Pastoral outra data',[['department_id'=>$cv_dep,'team_count'=>1]],301)['occurrence_ids'][0];
act($ev_leadA,['action'=>'claim_event_team','id'=>$cv_newRoot,'team_id'=>$cv_team]);$cv_openEvent=ev_child($cv_newRoot,$cv_team);$cv_openSlot=array_values(array_filter(ev_slots($cv_openEvent),fn($r)=>(int)$r['function_id']===$cv_sermon))[0]['id'];
act($admin,['action'=>'team','id'=>$cv_team,'name'=>'Equipe pastoral','department_id'=>$cv_dep,'leader_id'=>$ev_leadA,'mode'=>'assigned','members'=>[$cv_one,$cv_two],'functions'=>[['id'=>$cv_sermon,'quantity'=>1],['id'=>$cv_direction,'quantity'=>1]]]);
check($wpdb->get_var($wpdb->prepare('SELECT mode FROM '.CCS::table('events').' WHERE id=%d',$cv_openEvent))==='functions','changing default to directed does not close existing volunteering dates');
act($cv_one,['action'=>'claim','id'=>$cv_openSlot]);
act($cv_one,['action'=>'claim','id'=>$cv_newSlot],403);
act($admin,['action'=>'cancel_occurrence','id'=>$cv_root]);
cv_delegate($ev_leadA,$cv_preach,$cv_two,409);act($ev_leadA,['action'=>'clear_convocation','id'=>$cv_preach,'fingerprint'=>CCS::convocation_fingerprint($cv_preach),'confirm'=>true],409);
