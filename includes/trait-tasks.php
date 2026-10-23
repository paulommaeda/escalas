<?php
if (!defined('ABSPATH')) { exit; }
/** Preparation belongs to a concrete date; template changes never copy or erase its tasks. */
trait CCS_Tasks {
    public static function can_view_event_dashboard($o) {
        if (!self::management_access()) { return false; }
        if (self::pastor() || self::secretary()) { return true; }
        $rules=$o['rules_snapshot']??[];
        if (is_string($rules)) { $rules=json_decode($rules,true)?:[]; }
        foreach ($rules as $r) { if (self::manages_department($r['department_id'])) { return true; } }
        return false;
    }
    private static function manages_task_scope($o,$department) {
        if (!self::management_access() || self::secretary()) { return false; }
        if (self::pastor()) { return true; }
        if (!$department) { return self::can_manage_occurrence($o); }
        foreach ($o['rules_snapshot'] as $r) { if ((int)$r['department_id']===(int)$department) { return self::manages_department($department); } }
        return false;
    }
    public static function event_kind($value) {
        self::require_that(is_string($value) && in_array($value,['regular','special'],true),'Tipo de evento inválido.',400);
        return $value;
    }
    private static function task_record($id) {
        global $wpdb;
        $t=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('tasks')." WHERE id=%d AND status<>'deleted'",$id),ARRAY_A);
        self::require_that($t,'Tarefa não encontrada.',404);
        return $t;
    }
    private static function task_permissions($t,$o) {
        $live=$o['status']==='published' && ($o['event_kind']??'regular')==='special';
        $manager=self::manages_task_scope($o,(int)$t['department_id']);
        return ['can_edit'=>$live && $manager,'can_move'=>$live && ($manager || (!self::secretary() && (int)$t['assigned_id']===get_current_user_id())),'can_delete'=>$live && $manager];
    }
    public static function task_state() {
        global $wpdb;$list=[];$occurrences=[];
        foreach ($wpdb->get_results('SELECT t.* FROM '.self::table('tasks').' t JOIN '.self::table('occurrences')." o ON o.id=t.occurrence_id WHERE t.status<>'deleted' AND o.status<>'deleted' ORDER BY t.due_at,t.id",ARRAY_A) as $t) {
            $id=(int)$t['occurrence_id'];
            if (!isset($occurrences[$id])) { $occurrences[$id]=self::occurrence($id); }
            $o=$occurrences[$id];
            // A volunteer receives only their own tasks; management reads the complete event in scope.
            if (!self::can_view_event_dashboard($o) && (int)$t['assigned_id']!==get_current_user_id()) { continue; }
            $t=array_merge($t,self::task_permissions($t,$o));
            $t['event_title']=$o['title'];$t['event_starts']=$o['starts'];$t['event_status']=$o['status'];
            $t['assigned_name']=self::name($t['assigned_id']);
            $t['created_name']=self::name($t['created_by']);$t['updated_name']=self::name($t['updated_by']);
            $t['department_name']=$t['department_id']?((string)$wpdb->get_var($wpdb->prepare('SELECT name FROM '.self::table('departments').' WHERE id=%d',$t['department_id']))?:'Departamento'):'Organização geral';
            $team=$t['team_id']?self::team($t['team_id']):null;$t['team_name']=$team?$team['name']:'';
            foreach (['created_at','updated_at','completed_at'] as $k) { if ($t[$k]) { $t[$k]=get_date_from_gmt($t[$k]); } }
            $list[]=$t;
        }
        return $list;
    }
    private static function notify_task($t,$o,$type,$audit,$recipients) {
        $labels=['task_assigned'=>'Tarefa atribuída a você','task_updated'=>'Tarefa atualizada','task_moved'=>'Andamento de tarefa atualizado','task_deleted'=>'Tarefa retirada do evento'];
        self::notify_people($recipients,$type,$labels[$type],$t['title'].' · '.$o['title'].' · Prazo: '.mysql2date('d/m/Y H:i',$t['due_at']).'. Confira em Minhas tarefas.',$o['id'],0,'task:'.$audit,0,$t['id']);
    }
    private static function task_action($a) {
        global $wpdb;$id=absint($a['id']??0);$action=$a['action']??'';
        if ($action==='event_planning') {
            $o=self::occurrence($id);self::require_that(self::can_manage_occurrence($o));
            self::require_that($o['status']==='published','Este evento não pode mais ser alterado.',409);
            $kind=self::event_kind($a['event_kind']??'');
            self::require_that(($a['previous_kind']??null)===($o['event_kind']??'regular'),'A configuração mudou. Atualize antes de continuar.',409);
            if ($kind==='regular') {
                self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('tasks')." WHERE occurrence_id=%d AND status IN ('todo','doing') LIMIT 1",$id)),'Conclua ou exclua as tarefas pendentes antes de desativar o quadro.',409);
            }
            self::update('occurrences',['event_kind'=>$kind],['id'=>$id]);
            self::audit('event_planning',0,'occurrence='.$id.';kind='.$kind);
            return 'Configuração de tarefas do evento salva.';
        }
        self::require_that(in_array($action,['task_save','task_move','task_delete'],true),'Ação de tarefa inválida.',400);
        $old=$id?self::task_record($id):null;
        self::require_that($old || $action==='task_save','Tarefa não encontrada.',404);
        $occurrence=$old?(int)$old['occurrence_id']:absint($a['occurrence_id']??0);
        if ($old && isset($a['occurrence_id'])) { self::require_that(absint($a['occurrence_id'])===$occurrence,'A tarefa pertence a outra data.',400); }
        $o=self::occurrence($occurrence);
        self::require_that($o['status']==='published' && ($o['event_kind']??'regular')==='special','As tarefas estão disponíveis somente em eventos especiais ativos.',409);
        if ($old) {
            $permissions=self::task_permissions($old,$o);
            self::require_that($action==='task_move'?$permissions['can_move']:$permissions['can_edit']);
            self::require_that((int)($a['version']??0)===(int)$old['version'],'Esta tarefa mudou. Atualize o painel antes de salvar novamente.',409);
        }
        $status=$a['status']??($old['status']??'todo');
        if ($action==='task_delete') { self::require_that(($a['confirm']??false)===true,'Confirme a exclusão da tarefa.',400);$status='deleted'; }
        else { self::require_that(is_string($status) && in_array($status,['todo','doing','done'],true),'Etapa inválida.',400); }
        $data=['status'=>$status,'version'=>$old?(int)$old['version']+1:1,'updated_at'=>current_time('mysql',true),'updated_by'=>get_current_user_id(),'completed_at'=>$status==='done'?($old['completed_at']??current_time('mysql',true)):null];
        if ($action==='task_save') {
            $dep=absint($a['department_id']??0);$team=absint($a['team_id']??0);
            self::require_that(self::manages_task_scope($o,$dep));
            if ($dep) { self::event_rule($o,$dep);self::require_active('departments',$dep); }
            if ($team) { $t=self::team($team);self::require_that($dep && $t && !empty($t['active']) && (int)$t['department_id']===$dep,'A equipe precisa pertencer ao departamento da tarefa.',400); }
            $assignee=absint($a['assigned_id']??0);self::require_that(self::can_serve($assignee),'Escolha um responsável ativo que possa receber tarefas.',400);
            $description=sanitize_textarea_field($a['description']??'');self::require_that(mb_strlen($description)<=5000,'Descrição limitada a 5.000 caracteres.',400);
            $data=array_merge($data,['title'=>self::text($a,'title'),'description'=>$description,'department_id'=>$dep,'team_id'=>$team,'assigned_id'=>$assignee,'due_at'=>self::datetime($a['due_at']??'')->format('Y-m-d H:i:s')]);
        }
        if ($old) { self::update('tasks',$data,['id'=>$id]); }
        else { $id=self::insert('tasks',array_merge($data,['occurrence_id'=>$occurrence,'created_at'=>current_time('mysql',true),'created_by'=>get_current_user_id()])); }
        $final=array_merge($old?:[],$data,['id'=>$id]);
        $type=$action==='task_delete'?'task_deleted':($action==='task_move'?'task_moved':(!$old || (int)$old['assigned_id']!==(int)$final['assigned_id']?'task_assigned':'task_updated'));
        $audit=self::audit($type,0,'occurrence='.$occurrence.';task='.$id.';status='.$status);
        $recipients=$action==='task_move'?[$final['assigned_id'],$final['created_by']??get_current_user_id()]:[$final['assigned_id']];
        self::notify_task($final,$o,$type,$audit,$recipients);
        if ($old && (int)$old['assigned_id']!==(int)$final['assigned_id']) {
            self::notify_people([$old['assigned_id']],'task_updated','Responsável da tarefa alterado',$final['title'].' · '.$o['title'].'. Esta tarefa foi atribuída a '.self::name($final['assigned_id']).'.',$occurrence,0,'reassigned:'.$audit);
        }
        return ['message'=>$action==='task_delete'?'Tarefa excluída.':'Tarefa salva.','task_id'=>$id];
    }
}
