<?php
if (!defined('ABSPATH')) { exit; }
trait CCS_Events {
    public static function can_publish_event() { return self::can_catalog(); }
    public static function can_manage_occurrence($o) {
        if (self::secretary()) { return false; }
        if (self::pastor()) { return true; }
        $rules=$o['rules_snapshot']??[];
        if (is_string($rules)) { $rules=json_decode($rules,true)?:[]; }
        if (!$rules) { return false; }
        foreach ($rules as $rule) { if (!self::manages_department($rule['department_id'])) { return false; } }
        return true;
    }
    private static function event_requirements($items,$previous=[]) {
        self::require_that(is_array($items) && count($items)>=1 && count($items)<=30,'Selecione os departamentos necessários.',400);
        $rules=[];$seen=[];
        foreach ($items as $item) {
            self::require_that(is_array($item),'Departamento inválido.',400);
            $dep=absint($item['department_id']??0);$count=absint($item['team_count']??1);
            self::require_active('departments',$dep);
            self::require_that(self::manages_department($dep));
            self::require_that(!isset($seen[$dep]) && $count>=1 && $count<=30,'Confira os departamentos e a quantidade de equipes necessárias.',400);
            $seen[$dep]=true;
            $rule=['department_id'=>$dep,'required'=>true,'policy'=>'claim','team_count'=>$count,'team_ids'=>[],'selected_team_ids'=>[],'cursor'=>0];
            foreach ($previous as $old) { if ((int)$old['department_id']===$dep && self::requirement_count($old)===$count) { $rule=array_merge($old,['team_count'=>$count]);break; } }
            $rules[]=$rule;
        }
        return $rules;
    }
    private static function event_rule($o,$department) {
        foreach ($o['rules_snapshot'] as $rule) { if ((int)$rule['department_id']===(int)$department) { return $rule; } }
        self::fail('Este departamento não foi convocado para o evento.',403);
    }
    private static function requirement_count($rule) {
        return max(1,(int)($rule['team_count']??count($rule['selected_team_ids']??$rule['team_ids']??[])));
    }
    private static function occurrence_team_events($occurrence,$department) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT e.* FROM '.self::table('events').' e JOIN '.self::table('teams')." t ON t.id=e.team_id WHERE e.occurrence_id=%d AND t.department_id=%d AND e.status='published' ORDER BY e.id",$occurrence,$department),ARRAY_A);
    }
    private static function rule_allows_team($rule,$team) {
        if (in_array($rule['policy']??'manual',['manual','claim'],true)) { return true; }
        return in_array((int)$team,array_map('intval',$rule['selected_team_ids']??$rule['team_ids']??[]),true);
    }
    private static function department_team($department) {
        global $wpdb;
        $t=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('teams').' WHERE department_id=%d AND is_department=1 AND active=1 ORDER BY id LIMIT 1',$department),ARRAY_A);
        if ($t) { return $t; }
        $dep=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('departments').' WHERE id=%d AND active=1',$department),ARRAY_A);
        self::require_that($dep,'Departamento não encontrado.',404);
        $leader=self::can_serve($dep['leader_id'])?(int)$dep['leader_id']:get_current_user_id();
        $id=self::insert('teams',['department_id'=>$department,'name'=>$dep['name'],'leader_id'=>$leader,'mode'=>'open','active'=>1,'is_department'=>1]);
        self::insert('members',['team_id'=>$id,'user_id'=>$leader]);
        return self::team($id);
    }
    private static function join_occurrence($o,$team) {
        global $wpdb;
        self::require_that($o['status']==='published' && $o['starts']>current_time('mysql'),'Este evento foi cancelado ou já começou.',409);
        self::require_active('teams',$team['id']);self::require_that(self::leads_team($team['id']));
        $rule=self::event_rule($o,$team['department_id']);
        self::require_that(self::rule_allows_team($rule,$team['id']),'Esta data tem equipes fixas ou um rodízio já definido.',403);
        $current=self::occurrence_team_events($o['id'],$team['department_id']);
        foreach ($current as $row) { if ((int)$row['team_id']===(int)$team['id']) { return 'Sua equipe já assumiu esta data.'; } }
        self::require_that(count($current)<self::requirement_count($rule),'As equipes necessárias deste departamento já foram definidas. Atualize o evento.',409);
        $data=array_intersect_key($o,array_flip(['title','location','arrival','starts','ends']));
        $data['notes']='';
        $event=self::create_event($team['id'],$data,$team['mode'],$o['id']);
        $audit=self::audit('event_team_claimed',$event,'occurrence='.$o['id']);
        self::notify_department($team['department_id'],'team_claimed','Equipe confirmou a data',$team['name'].' assumiu a participação em '.$o['title'].'.',$o['id'],$event,'claim:'.$audit);
        return 'Participação assumida. A convocação segue o formato cadastrado da equipe.';
    }
    private static function legacy_schedule($a) {
        global $wpdb;self::require_that(self::can_publish_event());
        $teams=self::ids($a['team_ids']??[]);self::require_that($teams,'Selecione as equipes.',400);
        $rules=[];
        foreach ($teams as $id) {
            self::require_active('teams',$id);$team=self::team($id);self::require_that(self::manages_team($id));
            $dep=(int)$team['department_id'];
            if (!isset($rules[$dep])) { $rules[$dep]=['department_id'=>$dep,'required'=>true,'policy'=>'fixed','team_ids'=>[],'selected_team_ids'=>[],'team_count'=>0]; }
            $rules[$dep]['team_ids'][]=$id;$rules[$dep]['selected_team_ids'][]=$id;$rules[$dep]['team_count']++;
        }
        $start=self::datetime($a['starts']??'');$end=self::datetime($a['ends']??'');$arrival=self::datetime($a['arrival']??'');
        self::require_that($arrival<=$start && $end>$start && $start>current_datetime(),'Confira as datas e horários.',400);
        $repeat=absint($a['repeat']??1);$days=absint($a['interval']??7);
        self::require_that($repeat>=1 && $repeat<=12 && in_array($days,[7,14,28],true),'Repetição inválida.',400);
        for ($i=0;$i<$repeat;$i++) {
            $shift='+'.($i*$days).' days';
            $data=['title'=>self::text($a,'title'),'location'=>sanitize_text_field($a['location']??''),'arrival'=>$arrival->modify($shift)->format('Y-m-d H:i:s'),'starts'=>$start->modify($shift)->format('Y-m-d H:i:s'),'ends'=>$end->modify($shift)->format('Y-m-d H:i:s'),'notes'=>sanitize_textarea_field($a['notes']??'')];
            $occ=self::insert('occurrences',array_merge($data,['celebration_id'=>0,'rules_snapshot'=>wp_json_encode(array_values($rules)),'status'=>'published','created_by'=>get_current_user_id()]));
            foreach ($teams as $team) { $t=self::team($team);$mode=($a['mode']??'default')==='default'?$t['mode']:$a['mode'];self::create_event($team,$data,$mode,$occ); }
            self::audit('event_published',0,'occurrence='.$occ);
        }
        return 'Evento publicado com as participações das equipes selecionadas.';
    }
    private static function event_action($a) {
        global $wpdb;$id=absint($a['id']??0);
        switch ($a['action']??'') {
        case 'publish_event':
            self::require_that(self::can_publish_event());
            $rules=self::event_requirements($a['requirements']??[]);
            $start=self::datetime($a['starts']??'');$end=self::datetime($a['ends']??'');$arrival=self::datetime($a['arrival']??'');
            self::require_that($arrival<=$start && $end>$start && $start>current_datetime(),'Confira as datas e horários.',400);
            $count=absint($a['repeat']??1);$interval=absint($a['interval']??7);
            self::require_that($count>=1 && $count<=12 && in_array($interval,[7,14,28],true),'Repetição inválida.',400);
            $title=self::text($a,'title');$location=sanitize_text_field($a['location']??'');$created=[];
            for ($i=0;$i<$count;$i++) {
                $shift='+'.($i*$interval).' days';$starts=$start->modify($shift)->format('Y-m-d H:i:s');
                self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('occurrences')." WHERE title=%s AND location=%s AND starts=%s AND status='published'",$title,$location,$starts)),'Este evento já está publicado nesta data e local.',409);
                $occ=self::insert('occurrences',['celebration_id'=>0,'title'=>$title,'location'=>$location,'arrival'=>$arrival->modify($shift)->format('Y-m-d H:i:s'),'starts'=>$starts,'ends'=>$end->modify($shift)->format('Y-m-d H:i:s'),'notes'=>sanitize_textarea_field($a['notes']??''),'rules_snapshot'=>wp_json_encode($rules),'status'=>'published','created_by'=>get_current_user_id()]);
                self::audit('event_published',0,'occurrence='.$occ);self::notify_event_published($occ);$created[]=$occ;
            }
            return ['message'=>'Evento publicado. As lideranças já podem assumir as datas.','occurrence_ids'=>$created];
        case 'claim_event_team':
            $o=self::occurrence($id);$team=self::team(absint($a['team_id']??0));
            self::require_that($team && self::leads_team($team['id']));
            return self::join_occurrence($o,$team);
        case 'claim_event_department':
            $o=self::occurrence($id);$dep=absint($a['department_id']??0);
            self::require_that(self::manages_department($dep));self::event_rule($o,$dep);
            self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('teams').' WHERE department_id=%d AND active=1 AND is_department=0 LIMIT 1',$dep)),'Este departamento tem equipes. A participação deve ser assumida por uma delas.',409);
            return self::join_occurrence($o,self::department_team($dep));
        case 'release_event_team':
            $event=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('events').' WHERE id=%d',$id),ARRAY_A);
            self::require_that($event && self::can_manage_event($event));
            self::require_that($event['status']==='published' && $event['starts']>current_time('mysql'),'Esta participação não pode mais ser liberada.',409);
            self::cancel_event($id);
            return 'A participação desta equipe foi liberada. O evento e os outros departamentos foram preservados.';
        default:return self::celebration_action($a);
        }
    }
    public static function unified_event_state($events) {
        global $wpdb;$list=[];
        foreach ($wpdb->get_results('SELECT * FROM '.self::table('occurrences')." WHERE status<>'deleted' ORDER BY starts DESC",ARRAY_A) as $o) {
            $rules=json_decode($o['rules_snapshot'],true)?:[];$o['requirements']=[];
            $future=$o['status']==='published' && $o['starts']>current_time('mysql');
            $o['can_manage']=self::can_manage_occurrence($o);$o['can_edit']=$o['can_manage'] && $future;
            $o['fingerprint']=self::occurrence_fingerprint($o['id']);
            foreach ($rules as $r) {
                $dep=(int)$r['department_id'];$r['team_count']=self::requirement_count($r);
                $r['department_name']=$wpdb->get_var($wpdb->prepare('SELECT name FROM '.self::table('departments').' WHERE id=%d',$dep))?:'Departamento';
                $r['events']=array_values(array_filter($events,fn($ev)=>(int)$ev['occurrence_id']===(int)$o['id'] && (int)$ev['department_id']===$dep && ($o['status']==='published'?$ev['status']==='published':$ev['status']!=='deleted')));
                $r['remaining_teams']=max(0,$r['team_count']-count($r['events']));$r['missing_team']=$r['remaining_teams']>0;
                $r['can_assign']=$future && self::manages_department($dep);$r['claimable_teams']=[];$r['can_assume_department']=false;
                $r['fingerprint']=self::assignment_fingerprint($o['id'],$dep);$r['uncovered']=0;$r['pending']=0;
                foreach ($r['events'] as $ev) {
                    foreach ($wpdb->get_col($wpdb->prepare('SELECT status FROM '.self::table('slots')." WHERE event_id=%d AND status<>'withdrawn'",$ev['id'])) as $status) {
                        if (in_array($status,['available','declined'],true)) { $r['uncovered']++; }
                        if ($status==='pending') { $r['pending']++; }
                    }
                }
                $real_teams=0;
                foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('teams').' WHERE department_id=%d AND active=1',$dep),ARRAY_A) as $team) {
                    if (!(int)$team['is_department']) { $real_teams++; }
                    $assigned=array_filter($r['events'],fn($ev)=>(int)$ev['team_id']===(int)$team['id']);
                    if ($future && $r['missing_team'] && !$assigned && self::leads_team($team['id']) && self::rule_allows_team($r,$team['id'])) {
                        $r['claimable_teams'][]=['id'=>$team['id'],'name'=>$team['name'],'mode'=>$team['mode'],'is_department'=>(bool)$team['is_department']];
                    }
                }
                $r['can_assume_department']=$future && $r['missing_team'] && !$real_teams && self::manages_department($dep) && in_array($r['policy']??'manual',['claim','manual'],true) && !$r['claimable_teams'];
                $o['requirements'][]=$r;
            }
            unset($o['rules_snapshot']);$list[]=$o;
        }
        return $list;
    }
    public static function migrate_event_groups() {
        global $wpdb;
        if (get_option('ccs_unified_events')==='1') { return; }
        $wpdb->query('START TRANSACTION');
        try {
            $groups=[];
            foreach ($wpdb->get_results('SELECT * FROM '.self::table('events')." WHERE occurrence_id=0 AND status<>'deleted' ORDER BY id",ARRAY_A) as $event) {
                $key=hash('sha256',wp_json_encode(array_intersect_key($event,array_flip(['title','location','arrival','starts','ends','status']))));
                $groups[$key][]=$event;
            }
            foreach ($groups as $rows) {
                $first=$rows[0];$rules=[];
                foreach ($rows as $event) {
                    $team=self::team($event['team_id']);if (!$team) { continue; }
                    $dep=(int)$team['department_id'];
                    if (!isset($rules[$dep])) { $rules[$dep]=['department_id'=>$dep,'required'=>true,'policy'=>'claim','team_count'=>0,'team_ids'=>[],'selected_team_ids'=>[]]; }
                    if (!in_array((int)$team['id'],$rules[$dep]['team_ids'],true)) { $rules[$dep]['team_ids'][]=(int)$team['id'];$rules[$dep]['selected_team_ids'][]=(int)$team['id'];$rules[$dep]['team_count']++; }
                }
                if (!$rules) { continue; }
                $data=array_intersect_key($first,array_flip(['title','location','arrival','starts','ends','status']));
                $notes=array_unique(array_column($rows,'notes'));$data['notes']=count($notes)===1?(string)reset($notes):'';
                $occ=self::insert('occurrences',array_merge($data,['celebration_id'=>0,'rules_snapshot'=>wp_json_encode(array_values($rules)),'created_by'=>$first['created_by']]));
                foreach ($rows as $event) { self::update('events',['occurrence_id'=>$occ],['id'=>$event['id']]); }
            }
            self::require_that(false!==$wpdb->query('COMMIT'),'Não foi possível agrupar as escalas existentes.',500);
            update_option('ccs_unified_events','1',false);
        } catch (Throwable $e) { $wpdb->query('ROLLBACK');throw $e; }
    }
}
