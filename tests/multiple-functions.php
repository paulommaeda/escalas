<?php
// Several assignments in one concrete event, while unrelated overlapping dates still conflict.
function mf_slot($event,$function) { return array_values(array_filter(ev_slots($event),fn($s)=>(int)$s['function_id']===(int)$function))[0]['id']; }
function mf_assigned($root,$uid) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare('SELECT s.* FROM '.CCS::table('slots').' s JOIN '.CCS::table('events')." e ON e.id=s.event_id WHERE e.occurrence_id=%d AND e.status='published' AND s.assigned_id=%d AND s.status IN ('pending','confirmed') ORDER BY s.id",$root,$uid),ARRAY_A);
}
$mf_one=ev_user('multifuncao');$mf_two=ev_user('multifuncao_substituto');$mf_free=ev_user('multifuncao_outro');
foreach ([$mf_one,$mf_two,$mf_free] as $uid) { act($admin,['action'=>'person_functions','id'=>$uid,'function_ids'=>[$cv_sermon,$cv_direction]]); }
$mf_functions=[['id'=>$cv_sermon,'quantity'=>1],['id'=>$cv_direction,'quantity'=>1]];
act($admin,['action'=>'team','name'=>'Convocação multifunção','department_id'=>$cv_dep,'leader_id'=>$ev_leadA,'mode'=>'assigned','members'=>[$mf_one,$mf_two,$mf_free],'functions'=>$mf_functions]);$mf_directed=latest('teams');
act($admin,['action'=>'team','name'=>'Voluntariado multifunção','department_id'=>$cv_dep,'leader_id'=>$ev_leadA,'mode'=>'functions','members'=>[$mf_one,$mf_two,$mf_free],'functions'=>$mf_functions]);$mf_volunteer=latest('teams');
act($admin,['action'=>'department','name'=>'Apoio multifunção','leader_id'=>$ev_deptB]);$mf_dep=latest('departments');
act($admin,['action'=>'team','name'=>'Equipe inteira multifunção','department_id'=>$mf_dep,'leader_id'=>$ev_leadC,'mode'=>'roster','members'=>[$mf_one,$mf_two]]);$mf_roster=latest('teams');
$mf_rules=[['department_id'=>$cv_dep,'team_count'=>2],['department_id'=>$mf_dep,'team_count'=>1]];
$mf_root=ev_publish($admin,'Evento de múltiplas funções',$mf_rules,350)['occurrence_ids'][0];
act($ev_leadA,['action'=>'claim_event_team','id'=>$mf_root,'team_id'=>$mf_directed]);$mf_assignedEvent=ev_child($mf_root,$mf_directed);
$mf_a=mf_slot($mf_assignedEvent,$cv_sermon);$mf_b=mf_slot($mf_assignedEvent,$cv_direction);
cv_delegate($ev_leadA,$mf_a,$mf_one);cv_delegate($ev_leadA,$mf_b,$mf_one);
check(count(mf_assigned($mf_root,$mf_one))===2,'same person receives opening and closing functions in the same team');
act($ev_leadA,['action'=>'claim_event_team','id'=>$mf_root,'team_id'=>$mf_volunteer]);$mf_volunteerEvent=ev_child($mf_root,$mf_volunteer);
$mf_c=mf_slot($mf_volunteerEvent,$cv_sermon);$mf_d=mf_slot($mf_volunteerEvent,$cv_direction);
act($mf_one,['action'=>'claim','id'=>$mf_c]);act($mf_one,['action'=>'claim','id'=>$mf_d]);
check(count(mf_assigned($mf_root,$mf_one))===4,'volunteering allows two functions in another team of the same event');
act($ev_leadC,['action'=>'claim_event_team','id'=>$mf_root,'team_id'=>$mf_roster]);$mf_rosterEvent=ev_child($mf_root,$mf_roster);
check(count(mf_assigned($mf_root,$mf_one))===5,'whole-team publication allows a person already serving in another department of the event');
foreach (mf_assigned($mf_root,$mf_one) as $slot) { act($mf_one,['action'=>'respond','id'=>$slot['id'],'status'=>'confirmed']); }
check(count(array_filter(mf_assigned($mf_root,$mf_one),fn($s)=>$s['status']==='confirmed'))===5,'each function can be confirmed independently without same-event conflicts');
$mf_day=current_datetime()->modify('+350 days')->format('Y-m-d');
act($admin,ev_edit($mf_root,['arrival'=>$mf_day.'T16:30','starts'=>$mf_day.'T17:00','ends'=>$mf_day.'T19:00']));
check(count(array_filter(mf_assigned($mf_root,$mf_one),fn($s)=>$s['status']==='pending'))===5,'time edit resets every assignment even when one person serves in several teams');
foreach (mf_assigned($mf_root,$mf_one) as $slot) { act($mf_one,['action'=>'respond','id'=>$slot['id'],'status'=>'confirmed']); }
act($mf_one,['action'=>'respond','id'=>$mf_a,'status'=>'declined']);
act($mf_one,['action'=>'invite','id'=>$mf_a,'to_id'=>$mf_two]);$mf_invite=latest('invites');
act($mf_two,['action'=>'answer_invite','id'=>$mf_invite,'accept'=>true]);
check(count(mf_assigned($mf_root,$mf_two))===2,'replacement may accept a second same-event function while already in a roster');
// The exception uses the parent id, never the matching title, location or timestamps.
$mf_other=ev_publish($admin,'Outro evento sobreposto',[['department_id'=>$cv_dep,'team_count'=>2],['department_id'=>$mf_dep,'team_count'=>1]],350)['occurrence_ids'][0];
act($ev_leadA,['action'=>'claim_event_team','id'=>$mf_other,'team_id'=>$mf_directed]);$mf_otherDirected=ev_child($mf_other,$mf_directed);$mf_e=mf_slot($mf_otherDirected,$cv_sermon);
cv_delegate($ev_leadA,$mf_e,$mf_one,409);cv_delegate($ev_leadA,$mf_e,$mf_two,409);
cv_delegate($ev_leadA,$mf_e,$mf_free);
act($ev_leadA,['action'=>'claim_event_team','id'=>$mf_other,'team_id'=>$mf_volunteer]);$mf_otherVol=ev_child($mf_other,$mf_volunteer);$mf_f=mf_slot($mf_otherVol,$cv_sermon);
act($mf_one,['action'=>'claim','id'=>$mf_f,'occurrence_id'=>$mf_root],409);
act($ev_leadC,['action'=>'claim_event_team','id'=>$mf_other,'team_id'=>$mf_roster],409);
check(!ev_child($mf_other,$mf_roster),'conflicting roster claim leaves no partial team schedule');
// A previously declined slot may not be confirmed or swapped into another busy event.
CCS::update('slots',['original_id'=>$mf_one,'assigned_id'=>$mf_one,'status'=>'declined'],['id'=>$mf_f]);
act($mf_one,['action'=>'respond','id'=>$mf_f,'status'=>'confirmed'],409);
act($mf_one,['action'=>'invite','id'=>$mf_f,'to_id'=>$mf_two],409);
$mf_oldInvite=CCS::insert('invites',['slot_id'=>$mf_f,'from_id'=>$mf_one,'to_id'=>$mf_two,'status'=>'pending','created_at'=>current_time('mysql',true)]);
act($mf_two,['action'=>'answer_invite','id'=>$mf_oldInvite,'accept'=>true],409);
check(cv_slot($mf_f)['status']==='declined' && $wpdb->get_var($wpdb->prepare('SELECT status FROM '.CCS::table('invites').' WHERE id=%d',$mf_oldInvite))==='pending','cross-event replacement failure preserves slot and unanswered invitation');
// Move to another day where one of the assigned people is already committed.
$mf_next=ev_publish($admin,'Compromisso no dia seguinte',[['department_id'=>$cv_dep,'team_count'=>1]],351)['occurrence_ids'][0];
act($ev_leadA,['action'=>'claim_event_team','id'=>$mf_next,'team_id'=>$mf_directed]);$mf_nextEvent=ev_child($mf_next,$mf_directed);cv_delegate($ev_leadA,mf_slot($mf_nextEvent,$cv_sermon),$mf_one);
$mf_before=[$wpdb->get_row($wpdb->prepare('SELECT * FROM '.CCS::table('occurrences').' WHERE id=%d',$mf_root),ARRAY_A),mf_assigned($mf_root,$mf_one),mf_assigned($mf_root,$mf_two)];
$mf_nextDay=current_datetime()->modify('+351 days')->format('Y-m-d');
act($admin,ev_edit($mf_root,['arrival'=>$mf_nextDay.'T16:30','starts'=>$mf_nextDay.'T17:00','ends'=>$mf_nextDay.'T19:00']),409);
check($mf_before===[$wpdb->get_row($wpdb->prepare('SELECT * FROM '.CCS::table('occurrences').' WHERE id=%d',$mf_root),ARRAY_A),mf_assigned($mf_root,$mf_one),mf_assigned($mf_root,$mf_two)],'conflict on time edit rolls back all assignments and event fields');
// Touching intervals remain allowed: arrival at the exact previous end is not overlap.
$mf_after=act($admin,['action'=>'publish_event','title'=>'Depois da celebração','location'=>'Outro local','arrival'=>$mf_day.'T19:00','starts'=>$mf_day.'T19:30','ends'=>$mf_day.'T21:00','requirements'=>[['department_id'=>$cv_dep,'team_count'=>1]]])['occurrence_ids'][0];
act($ev_leadA,['action'=>'claim_event_team','id'=>$mf_after,'team_id'=>$mf_directed]);cv_delegate($ev_leadA,mf_slot(ev_child($mf_after,$mf_directed),$cv_sermon),$mf_one);
check(!CCS::conflict($mf_one,$mf_day.' 21:00:00',$mf_day.' 22:00:00'),'non-overlapping interval beginning at previous end remains free');
// Legacy children with occurrence_id=0 must share only their own assignments.
wp_set_current_user($admin);$mf_legacyDay=current_datetime()->modify('+370 days')->format('Y-m-d');
$mf_legacyData=['title'=>'Escala avulsa A','location'=>'Templo','arrival'=>$mf_legacyDay.' 17:30:00','starts'=>$mf_legacyDay.' 18:00:00','ends'=>$mf_legacyDay.' 20:00:00','notes'=>''];
$mf_legacyA=CCS::create_event($mf_directed,$mf_legacyData,'assigned');
cv_delegate($ev_leadA,mf_slot($mf_legacyA,$cv_sermon),$mf_free);cv_delegate($ev_leadA,mf_slot($mf_legacyA,$cv_direction),$mf_free);
$mf_legacyB=CCS::create_event($mf_directed,array_merge($mf_legacyData,['title'=>'Escala avulsa B']),'assigned');
cv_delegate($ev_leadA,mf_slot($mf_legacyB,$cv_sermon),$mf_free,409);
check(!CCS::conflict($mf_free,$mf_legacyData['arrival'],$mf_legacyData['ends'],mf_slot($mf_legacyA,$cv_sermon)),'slot-based compatibility resolves its own legacy schedule context');
