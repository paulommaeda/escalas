<?php
if (!defined('ABSPATH')) { exit; }
/** Celebration models are independent from concrete team schedules and their responses. */
trait CCS_Celebrations {
    public static function is_member($team,$user) {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare('SELECT user_id FROM '.self::table('members').' WHERE team_id=%d AND user_id=%d',$team,$user));
    }
    public static function can_publish_team($team,$mode) {
        $t=self::team($team);
        return !self::secretary() && $t && !empty($t['active']) && self::leads_team($team);
    }
    public static function can_manage_event($event) { return self::leads_team($event['team_id']); }
    public static function create_event($team,$data,$mode='roster',$occurrence=0) {
        global $wpdb; self::require_active('teams',$team);
        self::require_that(in_array($mode,['roster','open','functions'],true),'Modo de escala inválido.',400);
        $members=array_filter($wpdb->get_col($wpdb->prepare('SELECT user_id FROM '.self::table('members').' WHERE team_id=%d',$team)),[__CLASS__,'can_serve']);
        self::require_that($mode==='functions' || count($members)>0,'A equipe '.self::team($team)['name'].' não tem integrantes ativos.',400);
        if ($mode==='roster') {
            foreach ($members as $m) { self::require_that(!self::conflict($m,$data['arrival'],$data['ends']),'Conflito de horário: '.self::name($m).'. Nenhuma alteração deste envio foi salva.',409); }
        }
        if ($occurrence) { $data['notes']=''; }
        $event=self::insert('events',array_merge($data,['team_id'=>$team,'mode'=>$mode,'occurrence_id'=>$occurrence,'status'=>'published','created_by'=>get_current_user_id()]));
        if ($mode==='functions') {
            $functions=self::team_functions($team);
            self::require_that(count($functions)>0,'Cadastre as funções da equipe '.self::team($team)['name'].' antes de convocá-la.',400);
            foreach ($functions as $function) { for ($i=0;$i<(int)$function['quantity'];$i++) { self::insert('slots',['event_id'=>$event,'function_id'=>$function['id'],'function_name'=>$function['name'],'original_id'=>0,'assigned_id'=>0,'status'=>'available']); } }
        }
        elseif ($mode==='open') { self::insert('slots',['event_id'=>$event,'original_id'=>0,'assigned_id'=>0,'status'=>'available']); }
        else { foreach ($members as $m) { self::insert('slots',['event_id'=>$event,'original_id'=>$m,'assigned_id'=>$m,'status'=>'pending']); } }
        $audit=self::audit('schedule_published',$event,$mode);
        self::notify_team_publication($event,$audit);
        return $event;
    }
    private static function celebration($id) {
        global $wpdb;
        $c=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('celebrations').' WHERE id=%d',$id),ARRAY_A);
        self::require_that($c,'Celebração não encontrada.',404);
        $c['rules']=json_decode($c['rules'],true)?:[];
        return $c;
    }
    private static function occurrence($id) {
        global $wpdb;
        $o=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('occurrences').' WHERE id=%d',$id),ARRAY_A);
        self::require_that($o,'Ocorrência não encontrada.',404);
        $o['rules_snapshot']=json_decode($o['rules_snapshot'],true)?:[];
        return $o;
    }
    private static function validate_rule_teams($department,$ids) {
        foreach ($ids as $id) {
            $t=self::team($id); self::require_that($t && !empty($t['active']) && (int)$t['department_id']===(int)$department,'Uma equipe selecionada não pertence ao departamento.',400);
        }
    }
    private static function cancel_event($id,$notify=true,$department_notice=true) {
        global $wpdb;
        $event=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('events').' WHERE id=%d',$id),ARRAY_A);
        if (!$event || $event['status']!=='published') { return; }
        self::update('events',['status'=>'cancelled'],['id'=>$id]);
        $slots=$wpdb->get_col($wpdb->prepare('SELECT id FROM '.self::table('slots').' WHERE event_id=%d',$id));
        foreach ($slots as $slot) { self::update('invites',['status'=>'cancelled'],['slot_id'=>$slot,'status'=>'pending']); }
        $audit=self::audit('schedule_cancelled',$id);
        if ($notify) {
            $ids=array_merge(self::team_recipients($event['team_id']),$wpdb->get_col($wpdb->prepare('SELECT assigned_id FROM '.self::table('slots').' WHERE event_id=%d',$id)));
            self::notify_people($ids,'team_cancelled','Participação da equipe cancelada',$event['title'].'. Confira o evento para acompanhar as outras participações.',$event['occurrence_id'],$id,'cancel:'.$audit);
            if ($department_notice) {
                $team=self::team($event['team_id']);
                self::notify_department($team['department_id'],'department_pending','Departamento com participação pendente',$team['name'].' liberou a participação em '.$event['title'].'.',$event['occurrence_id'],$id,'pending:'.$audit);
            }
        }
    }
    public static function celebration_state($visible_events) {
        global $wpdb;
        $templates=[];
        // Models can be configured only by the pastor; department leaders see their own requirements.
        foreach ($wpdb->get_results('SELECT * FROM '.self::table('celebrations').' WHERE active=1 ORDER BY name',ARRAY_A) as $c) {
            $c['rules']=json_decode($c['rules'],true)?:[];
            if (!self::pastor() && !self::secretary()) { $c['rules']=array_values(array_filter($c['rules'],fn($r)=>self::manages_department($r['department_id']))); }
            if (!self::pastor() && !self::secretary() && !$c['rules']) { continue; }
            $templates[]=$c;
        }
        return ['celebrations'=>$templates,'occurrences'=>self::unified_event_state($visible_events)];
    }
    private static function build_plan($a,$c) {
        global $wpdb;
        $date=(string)($a['date']??'');
        $start=self::datetime($date.'T'.$c['starts_time']);
        $end=self::datetime($date.'T'.$c['ends_time']); if ($end<=$start) { $end=$end->modify('+1 day'); }
        $arrival=$start->modify('-'.(int)$c['arrival_minutes'].' minutes');
        self::require_that($start>current_datetime(),'Escolha uma data futura.',400);
        $count=absint($a['repeat']??1); $interval=absint($a['interval']??7);
        self::require_that($count>=1 && $count<=12 && in_array($interval,[7,14,28],true),'Repetição inválida.',400);
        $overrides=[];
        foreach ((array)($a['selections']??[]) as $selection) {
            self::require_that(is_array($selection),'Seleção inválida.',400);
            $i=absint($selection['index']??999); $dep=absint($selection['department_id']??0);
            self::require_that($i<$count && in_array($dep,array_map(fn($r)=>(int)$r['department_id'],$c['rules']),true),'Seleção fora desta celebração.',400);
            $key=$i.':'.$dep; self::require_that(!isset($overrides[$key]),'Seleção duplicada.',400);
            $ids=self::ids($selection['team_ids']??[]); self::validate_rule_teams($dep,$ids); $overrides[$key]=$ids;
        }
        $plan=[];
        for ($i=0;$i<$count;$i++) {
            $shift='+'.($i*$interval).' days';
            $data=['title'=>$c['name'],'location'=>$c['location'],'arrival'=>$arrival->modify($shift)->format('Y-m-d H:i:s'),'starts'=>$start->modify($shift)->format('Y-m-d H:i:s'),'ends'=>$end->modify($shift)->format('Y-m-d H:i:s'),'notes'=>sanitize_textarea_field($a['notes']??'')];
            self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('occurrences')." WHERE celebration_id=%d AND starts=%s AND status='published'",$c['id'],$data['starts'])),'Já existe uma ocorrência desta celebração nessa data e horário.',409);
            $rules=[];
            foreach ($c['rules'] as $r) {
                $ids=in_array($r['policy'],['manual','claim'],true)?[]:$r['team_ids'];
                if ($r['policy']==='rotation') { $ids=[$r['team_ids'][((int)$r['cursor']+$i)%count($r['team_ids'])]]; }
                $key=$i.':'.$r['department_id']; if (array_key_exists($key,$overrides)) { $ids=$overrides[$key]; }
                self::validate_rule_teams($r['department_id'],$ids);
                if (isset($r['team_count'])) { self::require_that(count($ids)<=$r['team_count'],'A seleção supera a quantidade de equipes prevista para este departamento.',409); }
                $r['selected_team_ids']=$ids;
                $r['department_name']=$wpdb->get_var($wpdb->prepare('SELECT name FROM '.self::table('departments').' WHERE id=%d',$r['department_id']));
                $rules[]=$r;
            }
            $plan[]=['index'=>$i,'data'=>$data,'rules'=>$rules];
        }
        return $plan;
    }
    private static function celebration_action($a) {
        global $wpdb;
        $id=absint($a['id']??0); $uid=get_current_user_id();
        switch ($a['action']??'') {
        case 'claim':
            $s=self::slot($id);
            self::require_that(in_array($s['mode'],['open','functions'],true) && self::is_member($s['team_id'],$uid),'Somente integrantes desta equipe podem escolher a vaga.',403);
            if ($s['mode']==='functions') { self::require_that(self::qualified($s['team_id'],$s['function_id'],$uid),'Você precisa estar cadastrado nesta função da equipe.',403); }
            self::require_that($s['status']==='available' && !(int)$s['assigned_id'],'Esta vaga já foi escolhida. Atualize a lista.',409);
            self::require_that(!self::conflict($uid,$s['arrival'],$s['ends'],$id),'Você já tem uma escala nesse horário.',409);
            self::update('slots',['assigned_id'=>$uid,'original_id'=>$uid,'status'=>'confirmed'],['id'=>$id]);
            self::audit('slot_claimed',$s['event_id'],'slot='.$id);
            return 'Vaga escolhida e participação confirmada.';
        case 'release_slot':
            $s=self::slot($id); self::require_that(in_array($s['mode'],['open','functions'],true) && (int)$s['assigned_id']===$uid);
            self::update('invites',['status'=>'cancelled'],['slot_id'=>$id,'status'=>'pending']);
            self::update('slots',['assigned_id'=>0,'original_id'=>0,'status'=>'available'],['id'=>$id]);
            self::audit('slot_released',$s['event_id'],'slot='.$id.';previous='.$uid);
            return 'A vaga está disponível novamente para os integrantes da equipe.';
        case 'celebration':
            self::require_that(self::pastor());
            if ($id) { self::require_active('celebrations',$id); } $old=$id?self::celebration($id):null;
            $rules=[];$seen=[];
            self::require_that(is_array($a['rules']??null) && count($a['rules'])>=1 && count($a['rules'])<=30,'Inclua de 1 a 30 departamentos.',400);
            foreach ($a['rules'] as $r) {
                $dep=absint($r['department_id']??0);
                self::require_that($dep && !in_array($dep,$seen,true) && $wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('departments').' WHERE active=1 AND id=%d',$dep)),'Departamento inválido ou repetido.',400); $seen[]=$dep;
                $policy=$r['policy']??'claim'; self::require_that(in_array($policy,['claim','manual','fixed','rotation'],true),'Regra inválida.',400);
                $ids=self::ids($r['team_ids']??[]); self::validate_rule_teams($dep,$ids);
                if (in_array($policy,['manual','claim'],true)) { $ids=[]; }
                self::require_that(in_array($policy,['manual','claim'],true) || count($ids)>0,'Escolha as equipes fixas ou a ordem do rodízio.',400);
                $team_count=$policy==='fixed'?count($ids):($policy==='rotation'?1:absint($r['team_count']??1));
                self::require_that($team_count>=1 && $team_count<=30,'Informe de 1 a 30 equipes por departamento.',400);
                $cursor=0;
                foreach (($old['rules']??[]) as $previous) {
                    if ((int)$previous['department_id']===$dep && $previous['policy']===$policy && $previous['team_ids']===$ids) { $cursor=(int)$previous['cursor']; }
                }
                $rules[]=['department_id'=>$dep,'required'=>!empty($r['required']),'policy'=>$policy,'team_ids'=>$ids,'cursor'=>$cursor,'team_count'=>$team_count];
            }
            $time=$a['starts_time']??'';$end=$a['ends_time']??'';
            foreach ([$time,$end] as $t) { self::require_that(is_string($t) && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$t),'Informe horários válidos.',400); }
            $minutes=absint($a['arrival_minutes']??30); self::require_that($minutes<=480,'Antecedência máxima: 480 minutos.',400);
            $data=['name'=>self::text($a,'name'),'location'=>sanitize_text_field($a['location']??''),'starts_time'=>$time,'ends_time'=>$end,'arrival_minutes'=>$minutes,'rules'=>wp_json_encode($rules),'revision'=>$old?(int)$old['revision']+1:1];
            if ($id) { self::require_that((int)($a['revision']??0)===(int)$old['revision'],'O cadastro mudou. Feche e atualize antes de editar.',409); self::update('celebrations',$data,['id'=>$id]); }
            else { $id=self::insert('celebrations',$data); }
            self::audit('celebration_saved',0,'celebration='.$id);
            return 'Celebração cadastrada. As regras serão usadas nas próximas datas geradas.';
        case 'celebration_preview':
        case 'create_occurrences':
            self::require_that(self::pastor());
            self::require_active('celebrations',$id); $c=self::celebration($id); $plan=self::build_plan($a,$c);
            if ($a['action']==='celebration_preview') { return ['preview'=>$plan,'revision'=>(int)$c['revision']]; }
            self::require_that((int)($a['revision']??0)===(int)$c['revision'],'As regras ou o rodízio mudaram. Gere a prévia novamente.',409);
            $created=[];
            foreach ($plan as $entry) {
                $data=$entry['data'];
                $occ=self::insert('occurrences',array_merge($data,['celebration_id'=>$id,'rules_snapshot'=>wp_json_encode($entry['rules']),'status'=>'published','created_by'=>get_current_user_id()]));
                $created[]=$occ;
                foreach ($entry['rules'] as $r) {
                    foreach ($r['selected_team_ids'] as $team) { self::create_event($team,$data,self::team($team)['mode'],$occ); }
                }
                self::audit('occurrence_created',0,'occurrence='.$occ);
                self::notify_event_published($occ);
            }
            foreach ($c['rules'] as &$r) { if ($r['policy']==='rotation') { $r['cursor']=((int)$r['cursor']+count($plan))%count($r['team_ids']); } } unset($r);
            self::update('celebrations',['rules'=>wp_json_encode($c['rules']),'revision'=>(int)$c['revision']+1],['id'=>$id]);
            return 'Datas geradas com suas escalas. Confira os departamentos que ainda estão sem equipe definida.';
        case 'assign_occurrence':
            $o=self::occurrence($id); $dep=absint($a['department_id']??0);
            self::require_that(self::manages_department($dep));
            self::require_that($o['status']==='published' && $o['starts']>current_time('mysql'),'Esta celebração foi cancelada ou já começou.',409);
            self::require_that(in_array($dep,array_map(fn($r)=>(int)$r['department_id'],$o['rules_snapshot']),true),'Este departamento não está previsto nesta celebração.',400);
            $ids=self::ids($a['team_ids']??[]); self::require_that(count($ids)<=30,'Selecione até 30 equipes.',400); self::validate_rule_teams($dep,$ids);
            $rule=self::event_rule($o,$dep);
            if (isset($rule['team_count'])) { self::require_that(count($ids)<=$rule['team_count'],'A seleção supera a quantidade de equipes prevista para este departamento.',409); }
            $events=$wpdb->get_results($wpdb->prepare('SELECT e.* FROM '.self::table('events').' e JOIN '.self::table('teams')." t ON t.id=e.team_id WHERE e.occurrence_id=%d AND t.department_id=%d AND e.status='published'",$id,$dep),ARRAY_A);
            // Optimistic guard: don't silently cancel a newly chosen volunteer or edited team assignment.
            $fingerprint=self::assignment_fingerprint($id,$dep);
            self::require_that(is_string($a['fingerprint']??null) && hash_equals($fingerprint,$a['fingerprint']),'A escala foi alterada. Atualize antes de trocar as equipes.',409);
            $current=array_map(fn($ev)=>(int)$ev['team_id'],$events); sort($current);$chosen=$ids;sort($chosen);
            if ($current===$chosen) { return 'As equipes desta data já estão definidas dessa forma.'; }
            foreach ($events as $event) { if (!in_array((int)$event['team_id'],$ids,true)) { self::cancel_event($event['id'],true,false); } }
            $data=array_intersect_key($o,array_flip(['title','location','arrival','starts','ends','notes']));
            foreach (array_diff($ids,$current) as $team) { self::create_event($team,$data,self::team($team)['mode'],$id); }
            foreach ($o['rules_snapshot'] as &$r) { if ((int)$r['department_id']===$dep) { $r['selected_team_ids']=$ids; } }unset($r);
            self::update('occurrences',['rules_snapshot'=>wp_json_encode($o['rules_snapshot'])],['id'=>$id]);
            $audit=self::audit('occurrence_teams_changed',0,'occurrence='.$id.';department='.$dep);
            self::notify_department($dep,'teams_changed','Participação do departamento atualizada','As equipes de '.$o['title'].' foram atualizadas. Confira a programação do evento.',$id,0,'teams:'.$audit);
            return 'Equipes desta data atualizadas. As participações mantidas preservam suas confirmações e convites.';
        case 'cancel_occurrence':
            $o=self::occurrence($id);self::require_that(self::can_manage_occurrence($o));
            self::require_that($o['status']==='published' && $o['starts']>current_time('mysql'),'Esta ocorrência não pode ser cancelada.',409);
            foreach ($wpdb->get_col($wpdb->prepare('SELECT id FROM '.self::table('events')." WHERE occurrence_id=%d AND status='published'",$id)) as $event) { self::cancel_event($event,false); }
            self::update('occurrences',['status'=>'cancelled'],['id'=>$id]);
            $audit=self::audit('occurrence_cancelled',0,'occurrence='.$id);self::notify_occurrence_changed($id,'event_cancelled',$audit);
            return 'Celebração e todas as suas escalas canceladas. O rodízio das próximas datas foi preservado.';
        default: self::fail('Ação não reconhecida.',400);
        }
    }
    private static function assignment_fingerprint($occ,$dep) {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare('SELECT e.id,e.team_id,s.id slot_id,s.assigned_id,s.status FROM '.self::table('events').' e JOIN '.self::table('teams').' t ON t.id=e.team_id LEFT JOIN '.self::table('slots')." s ON s.event_id=e.id WHERE e.occurrence_id=%d AND t.department_id=%d AND e.status='published' ORDER BY e.id,s.id",$occ,$dep),ARRAY_A);
        return hash('sha256',wp_json_encode($rows));
    }
}

