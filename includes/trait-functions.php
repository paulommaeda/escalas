<?php
if (!defined('ABSPATH')) { exit; }
trait CCS_Functions {
    public static function leads_team($id) {
        $t=self::team($id);
        return !self::secretary() && $t && (self::manages_team($id) || (in_array('ccs_team',wp_get_current_user()->roles,true) && (int)$t['leader_id']===get_current_user_id()));
    }
    public static function can_catalog() {
        return self::management_access() && !self::secretary();
    }
    public static function has_function($function,$user) {
        global $wpdb;
        return self::can_serve($user) && (bool)$wpdb->get_var($wpdb->prepare('SELECT q.user_id FROM '.self::table('qualifications').' q JOIN '.self::table('functions').' f ON f.id=q.function_id WHERE f.id=%d AND f.active=1 AND q.user_id=%d',$function,$user));
    }
    public static function team_functions($team) {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare('SELECT f.*,tf.quantity FROM '.self::table('functions').' f JOIN '.self::table('team_functions').' tf ON tf.function_id=f.id WHERE tf.team_id=%d AND f.active=1 ORDER BY f.name,f.id',$team),ARRAY_A);
        foreach ($rows as &$row) { $row['members']=array_map('intval',$wpdb->get_col($wpdb->prepare('SELECT user_id FROM '.self::table('qualifications').' WHERE function_id=%d',$row['id']))); } unset($row);
        return $rows;
    }
    public static function qualified($team,$function,$user) {
        global $wpdb;
        return self::has_function($function,$user) && self::is_member($team,$user);
    }
    public static function save_team_functions($team,$items) {
        global $wpdb;
        self::require_that(is_array($items) && count($items)<=100,'Lista de funções inválida.',400);
        $clean=[];
        foreach ($items as $item) {
            self::require_that(is_array($item),'Função inválida.',400);
            $fid=absint($item['id']??0);$qty=absint($item['quantity']??1);
            self::require_active('functions',$fid);
            self::require_that(!isset($clean[$fid]) && $qty>=1 && $qty<=30,'Confira as funções e quantidades (1 a 30).',400);
            $clean[$fid]=$qty;
        }
        // Published slots keep their function snapshots; removing a team default does not erase them.
        self::require_that(false!==$wpdb->delete(self::table('team_functions'),['team_id'=>$team]),'Falha ao atualizar funções.',500);
        foreach ($clean as $fid=>$qty) { self::insert('team_functions',['team_id'=>$team,'function_id'=>$fid,'quantity'=>$qty]); }
    }
    public static function migrate_global_functions() {
        global $wpdb;
        if (get_option('ccs_global_functions')==='1') { return; }
        $wpdb->query('START TRANSACTION');
        try {
            $known=[];
            foreach ($wpdb->get_results('SELECT * FROM '.self::table('functions').' ORDER BY id',ARRAY_A) as $f) {
                $key=mb_strtolower(trim($f['name']),'UTF-8');
                $target=(int)$f['id'];
                if ((int)$f['active']===1 && isset($known[$key])) { $target=$known[$key]; }
                elseif ((int)$f['active']===1) { $known[$key]=$target; }
                if ((int)$f['team_id'] && (int)$f['active']===1) {
                    self::require_that(false!==$wpdb->query($wpdb->prepare('INSERT INTO '.self::table('team_functions').' (team_id,function_id,quantity) VALUES (%d,%d,%d) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)',$f['team_id'],$target,$f['quantity'])),'Falha ao migrar funções das equipes.',500);
                }
                if ($target!==(int)$f['id']) {
                    self::require_that(false!==$wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.self::table('qualifications').' (function_id,user_id) SELECT %d,user_id FROM '.self::table('qualifications').' WHERE function_id=%d',$target,$f['id'])),'Falha ao migrar perfis.',500);
                    self::update('slots',['function_id'=>$target],['function_id'=>$f['id']]);
                    self::update('functions',['active'=>0],['id'=>$f['id']]);
                }
                self::update('functions',['team_id'=>0],['id'=>$f['id']]);
            }
            self::require_that(false!==$wpdb->query('COMMIT'),'Falha ao concluir migração.',500);
            update_option('ccs_global_functions','1');
        } catch (Throwable $e) { $wpdb->query('ROLLBACK');wp_cache_delete('ccs_global_functions','options');throw $e; }
    }
    public static function notifications() {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare('SELECT i.id,i.from_id,s.function_name,e.title,e.location,e.arrival,e.starts,e.ends,t.name team_name FROM '.self::table('invites').' i JOIN '.self::table('slots').' s ON s.id=i.slot_id JOIN '.self::table('events').' e ON e.id=s.event_id JOIN '.self::table('teams')." t ON t.id=e.team_id WHERE i.to_id=%d AND i.status='pending' AND s.status='declined' AND s.assigned_id=i.from_id AND e.status='published' AND e.starts>%s ORDER BY i.id DESC",get_current_user_id(),current_time('mysql')),ARRAY_A);
        foreach ($rows as &$row) { $row['from_name']=self::name($row['from_id']); } unset($row);
        return $rows;
    }
    private static function function_action($a) {
        global $wpdb;
        $id=absint($a['id']??0);
        switch ($a['action']??'') {
        case 'person_functions':
        case 'profile_functions':
            $uid=get_current_user_id();
            if ($a['action']==='person_functions') { self::require_that(self::can_catalog());$uid=$id; }
            self::require_that(self::can_serve($uid),'Escolha uma pessoa ativa que possa participar de escalas.',400);
            $ids=self::ids($a['function_ids']??[]);
            self::require_that(count($ids)<=100,'Selecione até 100 funções.',400);
            foreach ($ids as $fid) { self::require_active('functions',$fid); }
            self::require_that(false!==$wpdb->delete(self::table('qualifications'),['user_id'=>$uid]),'Falha ao salvar funções.',500);
            foreach ($ids as $fid) { self::insert('qualifications',['function_id'=>$fid,'user_id'=>$uid]); }
            self::audit('profile_functions',0,wp_json_encode(['user'=>$uid,'functions'=>$ids]));
            return 'Funções da pessoa atualizadas. As escalas já assumidas foram preservadas.';
        case 'team_functions':
            self::require_active('teams',$id);self::require_that(self::leads_team($id));
            self::save_team_functions($id,$a['functions']??[]);
            self::audit('team_functions',0,(string)$id);
            return 'Funções da equipe atualizadas para as próximas escalas.';
        case 'function':
            self::require_that(self::can_catalog());
            if ($id) { self::require_active('functions',$id); }
            $name=self::text($a,'name');
            self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('functions').' WHERE name=%s AND active=1 AND id<>%d',$name,$id)),'Já existe uma função com esse nome no cadastro geral.',409);
            $data=['team_id'=>0,'name'=>$name,'quantity'=>1,'active'=>1];
            if ($id) { self::update('functions',$data,['id'=>$id]); } else { $id=self::insert('functions',$data); }
            self::audit('function_saved',0,'function='.$id);
            return 'Função salva no cadastro geral.';
        case 'delegate':
            $s=self::slot($id); self::require_that($s['mode']==='functions' && self::leads_team($s['team_id']));
            self::require_that($s['status']==='available' && !(int)$s['assigned_id'],'Esta vaga já foi preenchida. Atualize a lista.',409);
            $to=absint($a['to_id']??0); self::require_that(self::qualified($s['team_id'],$s['function_id'],$to),'Escolha um integrante cadastrado nesta função.',400);
            self::require_that(!self::conflict($to,$s['arrival'],$s['ends'],$id),'O voluntário já está escalado neste horário.',409);
            self::update('slots',['original_id'=>$to,'assigned_id'=>$to,'status'=>'confirmed'],['id'=>$id]);
            self::audit('function_delegated',$s['event_id'],'slot='.$id.';to='.$to);
            return 'Função atribuída e confirmada. O voluntário pode declinar e solicitar uma troca.';
        case 'vacancies':
            $event=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('events').' WHERE id=%d',$id),ARRAY_A);
            self::require_that($event && $event['mode']==='functions' && self::leads_team($event['team_id']));
            self::require_that($event['status']==='published' && $event['starts']>current_time('mysql'),'Esta escala não pode mais ser alterada.',409);
            $fid=absint($a['function_id']??0); $function=null;foreach (self::team_functions($event['team_id']) as $candidate) { if ((int)$candidate['id']===$fid) { $function=$candidate;break; } }
            if (!$function) { $function=$wpdb->get_row($wpdb->prepare('SELECT f.* FROM '.self::table('functions').' f JOIN '.self::table('slots').' s ON s.function_id=f.id WHERE f.id=%d AND s.event_id=%d LIMIT 1',$fid,$id),ARRAY_A); }
            self::require_that($function,'Selecione uma função desta equipe.',400);
            $quantity=absint($a['quantity']??1); self::require_that($quantity<=30,'Máximo de 30 vagas por função.',400);
            $slots=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('slots')." WHERE event_id=%d AND function_id=%d AND status<>'withdrawn' ORDER BY id DESC",$id,$fid),ARRAY_A);
            $free=array_values(array_filter($slots,fn($s)=>$s['status']==='available' && !(int)$s['assigned_id']));
            self::require_that($quantity>=count($slots)-count($free),'Há vagas ocupadas. Peça aos voluntários para liberá-las antes de reduzir a quantidade.',409);
            for ($i=count($slots);$i<$quantity;$i++) { self::insert('slots',['event_id'=>$id,'function_id'=>$fid,'function_name'=>$function['name'],'original_id'=>0,'assigned_id'=>0,'status'=>'available']); }
            for ($i=0;$i<count($slots)-$quantity;$i++) { self::update('slots',['status'=>'withdrawn'],['id'=>$free[$i]['id']]); }
            self::audit('function_vacancies_changed',$id,'function='.$fid.';quantity='.$quantity);
            return 'Necessidades desta celebração atualizadas. O padrão da equipe foi preservado.';
        default: return self::celebration_action($a);
        }
    }
}
