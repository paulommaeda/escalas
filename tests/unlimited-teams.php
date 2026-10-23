<?php
// Real REST regressions: no team quota, including records created before this update.
act($admin,['action'=>'department','name'=>'Equipes sem limite','leader_id'=>$d]);$ut_dep=latest('departments');$ut_teams=[];
for ($ut_i=0;$ut_i<31;$ut_i++) {
    act($admin,['action'=>'team','name'=>'Equipe livre '.($ut_i+1),'department_id'=>$ut_dep,'leader_id'=>$ut_i===1?$ev_leadB:$ev_leadA,'mode'=>'open','members'=>[$ev_a]]);
    $ut_teams[]=latest('teams');
}
$ut_requirements=[['department_id'=>$ut_dep]];
$ut_root=ev_publish($admin,'Evento com equipes livres',$ut_requirements,400)['occurrence_ids'][0];
[$ut_state,$ut_event]=ev_visible_occurrence($ev_leadA,$ut_root);
check($ut_event['requirements'][0]['missing_team'] && !isset($ut_event['requirements'][0]['team_count']),'event requires a department without requiring a team quantity');
// Simulate an already-published event with the former limit still stored in its snapshot.
$ut_old=json_decode($wpdb->get_var($wpdb->prepare('SELECT rules_snapshot FROM '.CCS::table('occurrences').' WHERE id=%d',$ut_root)),true);
$ut_old[0]['team_count']=1;CCS::update('occurrences',['rules_snapshot'=>wp_json_encode($ut_old)],['id'=>$ut_root]);
act($ev_leadA,['action'=>'claim_event_team','id'=>$ut_root,'team_id'=>$ut_teams[0]]);$ut_first=ev_child($ut_root,$ut_teams[0]);$ut_slot=ev_slots($ut_first)[0]['id'];
act($ev_a,['action'=>'claim','id'=>$ut_slot]);
[$ut_state,$ut_event]=ev_visible_occurrence($ev_leadB,$ut_root);
check(!$ut_event['requirements'][0]['missing_team'] && array_map('intval',array_column($ut_event['requirements'][0]['claimable_teams'],'id'))===[$ut_teams[1]],'another leader still sees an opportunity after the former quota was filled');
act($ev_leadB,['action'=>'claim_event_team','id'=>$ut_root,'team_id'=>$ut_teams[1]]);$ut_second=ev_child($ut_root,$ut_teams[1]);
act($ev_leadB,['action'=>'claim_event_team','id'=>$ut_root,'team_id'=>$ut_teams[1]]);
check(count(ev_slots($ut_second))===1,'repeated unlimited claim creates no duplicate vacancy');
act($ev_leadB,['action'=>'release_event_team','id'=>$ut_second]);
check(!(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.CCS::table('notifications')." WHERE occurrence_id=%d AND type='department_pending'",$ut_root)),'releasing one team does not falsely mark a department with another team as pending');
[$ut_state,$ut_event]=ev_visible_occurrence($admin,$ut_root);$ut_fingerprint=$ut_event['requirements'][0]['fingerprint'];
act($admin,['action'=>'assign_occurrence','id'=>$ut_root,'department_id'=>$ut_dep,'team_ids'=>$ut_teams,'fingerprint'=>$ut_fingerprint]);
[$ut_state,$ut_event]=ev_visible_occurrence($admin,$ut_root);
check(count($ut_event['requirements'][0]['events'])===31 && !$ut_event['requirements'][0]['missing_team'],'department assignment accepts more than thirty teams on an older event');
check(ev_child($ut_root,$ut_teams[0])===$ut_first && ev_slots($ut_first)[0]['status']==='confirmed','adding unlimited teams preserves existing participation and confirmation');
act($admin,['action'=>'assign_occurrence','id'=>$ut_root,'department_id'=>$ut_dep,'team_ids'=>[],'fingerprint'=>$ut_fingerprint],409);
act($admin,['action'=>'assign_occurrence','id'=>$ut_root,'department_id'=>$ut_dep,'team_ids'=>[$ev_teamA],'fingerprint'=>$ut_event['requirements'][0]['fingerprint']],400);
act($s,['action'=>'assign_occurrence','id'=>$ut_root,'department_id'=>$ut_dep,'team_ids'=>$ut_teams,'fingerprint'=>$ut_event['requirements'][0]['fingerprint']],403);
act($admin,ev_edit($ut_root,['requirements'=>$ut_requirements]));
$ut_rules=json_decode($wpdb->get_var($wpdb->prepare('SELECT rules_snapshot FROM '.CCS::table('occurrences').' WHERE id=%d',$ut_root)),true);
check(!isset($ut_rules[0]['team_count']) && ev_child($ut_root,$ut_teams[0])===$ut_first && ev_slots($ut_first)[0]['status']==='confirmed','editing departments without quantity preserves every existing team and confirmation');
[$ut_state,$ut_event]=ev_visible_occurrence($d,$ut_root);
act($d,['action'=>'assign_occurrence','id'=>$ut_root,'department_id'=>$ut_dep,'team_ids'=>[],'fingerprint'=>$ut_event['requirements'][0]['fingerprint']]);
[$ut_state,$ut_event]=ev_visible_occurrence($ev_leadB,$ut_root);
check($ut_event['requirements'][0]['missing_team'] && count($ut_event['requirements'][0]['claimable_teams'])===1,'department becomes pending again after its final team is removed');

// Existing manual templates accept any number of preview overrides and publish all of them.
$ut_model=['action'=>'celebration','name'=>'Celebração com equipes livres','location'=>'Templo','starts_time'=>'18:00','ends_time'=>'20:00','arrival_minutes'=>30,'rules'=>[['department_id'=>$ut_dep,'required'=>true,'policy'=>'manual','team_ids'=>[]]]];
act($admin,$ut_model);$ut_template=latest('celebrations');
$ut_stored=json_decode($wpdb->get_var($wpdb->prepare('SELECT rules FROM '.CCS::table('celebrations').' WHERE id=%d',$ut_template)),true);
check(!isset($ut_stored[0]['team_count']),'celebration registration no longer stores a required team quantity');
$ut_stored[0]['team_count']=1;CCS::update('celebrations',['rules'=>wp_json_encode($ut_stored)],['id'=>$ut_template]);
$ut_date=current_datetime()->modify('+410 days')->format('Y-m-d');
$ut_generate=['id'=>$ut_template,'date'=>$ut_date,'selections'=>[['index'=>0,'department_id'=>$ut_dep,'team_ids'=>$ut_teams]]];
$ut_preview=act($admin,array_merge($ut_generate,['action'=>'celebration_preview']));
check(count($ut_preview['preview'][0]['rules'][0]['selected_team_ids'])===31 && !isset($ut_preview['preview'][0]['rules'][0]['team_count']),'older manual template preview accepts more than thirty selected teams');
act($admin,array_merge($ut_generate,['action'=>'create_occurrences','revision'=>$ut_preview['revision']]));$ut_manualRoot=latest('occurrences');
[$ut_state,$ut_event]=ev_visible_occurrence($admin,$ut_manualRoot);
check(count($ut_event['requirements'][0]['events'])===31 && !$ut_event['requirements'][0]['missing_team'],'publication creates every team selected in the unlimited preview');
check((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.CCS::table('notifications')." WHERE occurrence_id=%d AND type='event_published' AND user_id IN (%d,%d)",$ut_manualRoot,$ev_leadA,$ev_leadB))===2,'manual publication keeps both team leaders informed even with teams preselected');

// Fixed teams and rotation retain their automatic selection rules, without an arbitrary list size.
$ut_revision=(int)$wpdb->get_var($wpdb->prepare('SELECT revision FROM '.CCS::table('celebrations').' WHERE id=%d',$ut_template));
$ut_model['rules'][0]['policy']='fixed';$ut_model['rules'][0]['team_ids']=$ut_teams;
act($admin,array_merge($ut_model,['id'=>$ut_template,'revision'=>$ut_revision]));
$ut_fixedDate=current_datetime()->modify('+420 days')->format('Y-m-d');
$ut_preview=act($admin,['action'=>'celebration_preview','id'=>$ut_template,'date'=>$ut_fixedDate]);
check(count($ut_preview['preview'][0]['rules'][0]['selected_team_ids'])===31,'fixed template automatically selects all thirty-one configured teams');
act($admin,['action'=>'create_occurrences','id'=>$ut_template,'date'=>$ut_fixedDate,'revision'=>$ut_preview['revision']]);$ut_fixedRoot=latest('occurrences');
act($ev_leadB,['action'=>'release_event_team','id'=>ev_child($ut_fixedRoot,$ut_teams[1])]);
[$ut_state,$ut_event]=ev_visible_occurrence($ev_leadB,$ut_fixedRoot);
check($ut_event['requirements'][0]['missing_team'] && count($ut_event['requirements'][0]['events'])===30 && array_map('intval',array_column($ut_event['requirements'][0]['claimable_teams'],'id'))===[$ut_teams[1]],'fixed template still highlights a specifically scheduled team that releases its participation');
act($ev_leadB,['action'=>'claim_event_team','id'=>$ut_fixedRoot,'team_id'=>$ut_teams[1]]);
[$ut_state,$ut_event]=ev_visible_occurrence($ev_leadB,$ut_fixedRoot);check(!$ut_event['requirements'][0]['missing_team'],'fixed department resolves when the expected team assumes its date again');
