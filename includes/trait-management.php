<?php
if (!defined('ABSPATH')) { exit; }
trait CCS_Management {
    public static function require_active($table,$id) {
        global $wpdb;
        self::require_that((bool)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table($table).' WHERE id=%d AND active=1',$id)),'Cadastro não encontrado ou já excluído.',404);
    }
    public static function occurrence_fingerprint($id) {
        global $wpdb;
        $o=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('occurrences').' WHERE id=%d',$id),ARRAY_A);
        $rows=$wpdb->get_results($wpdb->prepare('SELECT e.*,s.id slot_id,s.assigned_id,s.status slot_status FROM '.self::table('events').' e LEFT JOIN '.self::table('slots')." s ON s.event_id=e.id WHERE e.occurrence_id=%d AND e.status='published' ORDER BY e.id,s.id",$id),ARRAY_A);
        return hash('sha256',wp_json_encode([$o,$rows]));
    }
    private static function remove_event($id) {
        self::cancel_event($id);self::update('events',['status'=>'deleted'],['id'=>$id]);self::audit('schedule_deleted',$id);
    }
    private static function management_action($a) {
        global $wpdb;$id=absint($a['id']??0);
        if (($a['action']??'')==='edit_occurrence') {
            self::require_that(self::pastor());$o=self::occurrence($id);
            self::require_that($o['status']==='published' && $o['starts']>current_time('mysql'),'Somente uma celebração futura e ativa pode ser editada.',409);
            self::require_that(is_string($a['fingerprint']??null) && hash_equals(self::occurrence_fingerprint($id),$a['fingerprint']),'A celebração ou suas participações mudaram. Feche e atualize antes de editar.',409);
            $start=self::datetime($a['starts']??'');$end=self::datetime($a['ends']??'');$arrival=self::datetime($a['arrival']??'');
            self::require_that($arrival<=$start && $end>$start && $start>current_datetime(),'Confira as datas e horários.',400);
            $data=['title'=>self::text($a,'title'),'location'=>sanitize_text_field($a['location']??''),'notes'=>sanitize_textarea_field($a['notes']??''),'arrival'=>$arrival->format('Y-m-d H:i:s'),'starts'=>$start->format('Y-m-d H:i:s'),'ends'=>$end->format('Y-m-d H:i:s')];
            self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('occurrences')." WHERE celebration_id=%d AND starts=%s AND status='published' AND id<>%d",$o['celebration_id'],$data['starts'],$id)),'Já existe esta celebração nesse horário.',409);
            $changed=$data['starts']!==$o['starts'] || $data['arrival']!==$o['arrival'] || $data['ends']!==$o['ends'];
            $events=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('events')." WHERE occurrence_id=%d AND status='published'",$id),ARRAY_A);
            $ids=array_map(fn($e)=>(int)$e['id'],$events);$seen=[];
            foreach ($events as $event) {
                if ($changed) {
                    $slots=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('slots')." WHERE event_id=%d AND status<>'withdrawn'",$event['id']),ARRAY_A);
                    foreach ($slots as $slot) {
                        $user=(int)$slot['assigned_id'];
                        if ($user && in_array($slot['status'],['pending','confirmed'],true)) {
                            self::require_that(!isset($seen[$user]),'Uma pessoa está atribuída a mais de uma função nesta celebração. Ajuste as atribuições antes de alterar os horários.',409);$seen[$user]=true;
                            $sql='SELECT s.id FROM '.self::table('slots').' s JOIN '.self::table('events')." e ON e.id=s.event_id WHERE s.assigned_id=%d AND s.status IN ('pending','confirmed') AND e.status='published' AND e.arrival<%s AND e.ends>%s AND e.id NOT IN (".implode(',',array_fill(0,count($ids),'%d')).') LIMIT 1';
                            self::require_that(!$wpdb->get_var($wpdb->prepare($sql,array_merge([$user,$data['ends'],$data['arrival']],$ids))),'Conflito de horário: '.self::name($user).'. Nenhuma equipe foi alterada.',409);
                            self::update('slots',['status'=>self::can_serve($user)?'pending':'declined'],['id'=>$slot['id']]);
                        }
                        self::update('invites',['status'=>'cancelled'],['slot_id'=>$slot['id'],'status'=>'pending']);
                    }
                }
                self::update('events',$data,['id'=>$event['id']]);self::audit('celebration_date_edited',$event['id'],wp_json_encode(['before'=>$event,'after'=>$data,'confirmation_reset'=>$changed]));
            }
            self::update('occurrences',$data,['id'=>$id]);self::audit('occurrence_edited',0,'occurrence='.$id);
            return $changed?'Data e horários atualizados em todas as equipes. As pessoas atribuídas precisam confirmar novamente.':'Celebração e escalas vinculadas atualizadas.';
        }
        if (($a['action']??'')!=='delete') { return self::function_action($a); }
        self::require_that(($a['confirm']??false)===true,'Confirme a exclusão para continuar.',400);
        $type=$a['type']??'';
        switch ($type) {
        case 'schedule':
            $e=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('events').' WHERE id=%d',$id),ARRAY_A);
            self::require_that($e && self::can_manage_event($e));self::require_that($e['status']!=='deleted','Esta escala já foi excluída.',409);
            self::remove_event($id);return 'Escala excluída da agenda. O histórico foi preservado.';
        case 'occurrence':
            self::require_that(self::pastor());$o=self::occurrence($id);self::require_that($o['status']!=='deleted','Esta data já foi excluída.',409);
            foreach ($wpdb->get_col($wpdb->prepare('SELECT id FROM '.self::table('events')." WHERE occurrence_id=%d AND status<>'deleted'",$id)) as $event) { self::remove_event($event); }
            self::update('occurrences',['status'=>'deleted'],['id'=>$id]);break;
        case 'celebration':
            self::require_that(self::pastor());self::require_active('celebrations',$id);
            self::update('celebrations',['active'=>0],['id'=>$id]);break;
        case 'department':
            self::require_that(self::pastor());self::require_active('departments',$id);
            self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('teams').' WHERE department_id=%d AND active=1',$id)),'Exclua primeiro as equipes deste departamento.',409);
            foreach ($wpdb->get_results('SELECT * FROM '.self::table('celebrations').' WHERE active=1',ARRAY_A) as $c) {
                foreach (json_decode($c['rules'],true)?:[] as $r) { self::require_that((int)$r['department_id']!==$id,'Retire este departamento do cadastro da celebração “'.$c['name'].'” antes de excluí-lo.',409); }
            }
            self::update('departments',['active'=>0],['id'=>$id]);break;
        case 'team':
            self::require_that(self::manages_team($id));self::require_active('teams',$id);
            self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('events')." WHERE team_id=%d AND status='published' AND ends>%s",$id,current_time('mysql'))),'Cancele ou exclua as escalas em andamento/futuras desta equipe antes de excluí-la.',409);
            foreach ($wpdb->get_results('SELECT * FROM '.self::table('celebrations').' WHERE active=1',ARRAY_A) as $c) {
                foreach (json_decode($c['rules'],true)?:[] as $r) { self::require_that(!in_array($id,array_map('intval',$r['team_ids']??[]),true),'Retire esta equipe das regras da celebração “'.$c['name'].'” antes de excluí-la.',409); }
            }
            self::update('teams',['active'=>0],['id'=>$id]);break;
        case 'function':
            self::require_that(self::can_catalog());self::require_active('functions',$id);
            self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT tf.team_id FROM '.self::table('team_functions').' tf JOIN '.self::table('teams').' t ON t.id=tf.team_id WHERE tf.function_id=%d AND t.active=1',$id)),'Retire esta função das equipes antes de excluí-la do cadastro geral.',409);
            self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT s.id FROM '.self::table('slots').' s JOIN '.self::table('events')." e ON e.id=s.event_id WHERE s.function_id=%d AND s.status<>'withdrawn' AND e.status='published' AND e.ends>%s",$id,current_time('mysql'))),'Esta função tem vagas em escalas ativas. Remova essas necessidades ou cancele/exclua as escalas antes.',409);
            self::update('functions',['active'=>0],['id'=>$id]);break;
        case 'user':
            self::require_that(self::pastor());$u=get_userdata($id);
            self::require_that($u && self::has_profile($u),'Voluntário não encontrado.',404);
            self::require_that($id!==get_current_user_id() && !user_can($id,'manage_options'),'Não é permitido excluir sua própria conta ou um administrador por este painel.',403);
            self::require_that(get_user_meta($id,'ccs_deleted',true)!=='1','Este voluntário já foi excluído.',409);
            foreach (['departments','teams'] as $table) { self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table($table).' WHERE leader_id=%d AND active=1',$id)),'Troque a liderança dos departamentos/equipes deste voluntário antes de excluí-lo.',409); }
            // Reuse deactivation: reopen future open/function slots and cancel outstanding invitations.
            self::mutate(['action'=>'user','id'=>$id,'name'=>$u->display_name,'role'=>current(array_intersect($u->roles,['ccs_pastor','ccs_department','ccs_team','ccs_volunteer','ccs_secretary']))?:'ccs_volunteer','active'=>false]);
            update_user_meta($id,'ccs_deleted','1');self::revoke_all_keys($id);
            $wpdb->delete(self::table('members'),['user_id'=>$id]);$wpdb->delete(self::table('qualifications'),['user_id'=>$id]);
            // Keep the WordPress account and authorship; access to the scheduling app is disabled.
            break;
        default:self::fail('Tipo de exclusão inválido.',400);
        }
        self::audit($type.'_deleted',0,(string)$id);
        return $type==='celebration'?'Cadastro da celebração excluído. As datas já publicadas continuam na agenda.':'Item excluído dos cadastros ativos. O histórico foi preservado.';
    }
}
