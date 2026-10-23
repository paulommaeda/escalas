<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/trait-celebrations.php';
require_once __DIR__.'/trait-registration.php';
require_once __DIR__.'/trait-functions.php';
require_once __DIR__.'/trait-auth.php';
require_once __DIR__.'/trait-passkeys.php';
require_once __DIR__.'/trait-management.php';
require_once __DIR__.'/trait-import.php';
require_once __DIR__.'/class-ccs-mail.php';
require_once __DIR__.'/trait-events.php';
require_once __DIR__.'/trait-notifications.php';
final class CCS {
    use CCS_Celebrations, CCS_Registration, CCS_Functions, CCS_Auth, CCS_Passkeys, CCS_Management, CCS_Import, CCS_Events, CCS_Notifications;
    public static function table($name) { global $wpdb; return $wpdb->prefix . 'ccs_' . $name; }
    public static function boot() {
        self::auth_boot();
        CCS_Mail::boot();
        self::push_boot();
        add_action('admin_post_ccs_import_template',[__CLASS__,'import_template']);
        add_action('plugins_loaded', [__CLASS__, 'maybe_upgrade'], 5);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_ccs_settings', [__CLASS__, 'settings']);
        add_action('template_redirect', [__CLASS__, 'page']);
        add_shortcode('ccshalom_escalas', [__CLASS__, 'shortcode']);
        add_action('login_footer', [__CLASS__, 'signup_link']);
        add_action('admin_post_nopriv_ccs_register', [__CLASS__, 'register_account']);
        add_action('admin_post_ccs_register', [__CLASS__, 'register_account']);
        add_filter('login_message', [__CLASS__, 'registration_message']);
        add_action('login_enqueue_scripts', [__CLASS__, 'login_style']);
        add_filter('login_headerurl', fn() => home_url('/'));
        add_filter('login_headertext', fn() => 'CCShalom');
        add_filter('login_redirect', function($url, $requested, $user) {
            return $user instanceof WP_User && self::participant($user->ID) && !user_can($user, 'manage_options') ? self::url() : $url;
        }, 10, 3);
        add_filter('rest_post_dispatch', function($response, $server, $request) {
            if (str_starts_with($request->get_route(), '/ccshalom/v1/')) {
                $response->header('Cache-Control', 'no-store, private, max-age=0');
                $response->header('Vary', 'Cookie');
            }
            return $response;
        }, 10, 3);
    }
    public static function maybe_upgrade() {
        if (get_option('ccs_db_version')===CCS_VERSION) { return; }
        global $wpdb;
        $lock='ccs_schema_'.md5(DB_NAME.$wpdb->prefix);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)',$lock))!==1) { wp_die('Atualização das escalas em andamento. Recarregue em alguns segundos.'); }
        try { if (get_option('ccs_db_version')!==CCS_VERSION) { self::activate(); } }
        finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
    }
    public static function activate() {
        if (is_multisite()) { wp_die('Nesta versão, instale em um WordPress individual (sem multisite).'); }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $schemas = [
            'passkeys' => "id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, credential_hash varchar(64) NOT NULL, credential_id text NOT NULL, public_key text NOT NULL, sign_count bigint unsigned NOT NULL DEFAULT 0, label varchar(100) NOT NULL, created_at datetime NOT NULL, last_used datetime NULL, PRIMARY KEY  (id), UNIQUE KEY credential_hash (credential_hash), KEY user_id (user_id)",
            'departments' => 'active tinyint NOT NULL DEFAULT 1, id bigint unsigned NOT NULL AUTO_INCREMENT, name varchar(150) NOT NULL, leader_id bigint unsigned NOT NULL DEFAULT 0, PRIMARY KEY  (id)',
            'teams' => "is_department tinyint NOT NULL DEFAULT 0, active tinyint NOT NULL DEFAULT 1, id bigint unsigned NOT NULL AUTO_INCREMENT, department_id bigint unsigned NOT NULL, name varchar(150) NOT NULL, leader_id bigint unsigned NOT NULL DEFAULT 0, PRIMARY KEY  (id), mode varchar(20) NOT NULL DEFAULT 'roster', KEY department_id (department_id)",
            'functions' => 'id bigint unsigned NOT NULL AUTO_INCREMENT, team_id bigint unsigned NOT NULL, name varchar(150) NOT NULL, quantity int unsigned NOT NULL DEFAULT 1, active tinyint NOT NULL DEFAULT 1, PRIMARY KEY  (id), KEY team_id (team_id)',
            'team_functions' => 'team_id bigint unsigned NOT NULL, function_id bigint unsigned NOT NULL, quantity int unsigned NOT NULL DEFAULT 1, PRIMARY KEY  (team_id,function_id), KEY function_id (function_id)',
            'qualifications' => 'function_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, PRIMARY KEY  (function_id,user_id)',
            'members' => 'team_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, PRIMARY KEY  (team_id,user_id)',
            'events' => "occurrence_id bigint unsigned NOT NULL DEFAULT 0, mode varchar(20) NOT NULL DEFAULT 'roster', id bigint unsigned NOT NULL AUTO_INCREMENT, team_id bigint unsigned NOT NULL, title varchar(150) NOT NULL, location varchar(200) NOT NULL DEFAULT '', arrival datetime NOT NULL, starts datetime NOT NULL, ends datetime NOT NULL, notes text NOT NULL, status varchar(20) NOT NULL DEFAULT 'published', created_by bigint unsigned NOT NULL, PRIMARY KEY  (id), KEY team_id (team_id), KEY occurrence_id (occurrence_id), KEY starts (starts)",
            'slots' => "function_id bigint unsigned NOT NULL DEFAULT 0, function_name varchar(150) NOT NULL DEFAULT '', id bigint unsigned NOT NULL AUTO_INCREMENT, event_id bigint unsigned NOT NULL, original_id bigint unsigned NOT NULL, assigned_id bigint unsigned NOT NULL, status varchar(20) NOT NULL DEFAULT 'pending', PRIMARY KEY  (id), KEY event_id (event_id), KEY assigned_id (assigned_id)",
            'invites' => "id bigint unsigned NOT NULL AUTO_INCREMENT, slot_id bigint unsigned NOT NULL, from_id bigint unsigned NOT NULL, to_id bigint unsigned NOT NULL, status varchar(20) NOT NULL DEFAULT 'pending', created_at datetime NOT NULL, PRIMARY KEY  (id), KEY slot_id (slot_id), KEY to_id (to_id)",
            'celebrations' => "active tinyint NOT NULL DEFAULT 1, id bigint unsigned NOT NULL AUTO_INCREMENT, name varchar(150) NOT NULL, location varchar(200) NOT NULL DEFAULT '', starts_time varchar(5) NOT NULL, ends_time varchar(5) NOT NULL, arrival_minutes int unsigned NOT NULL DEFAULT 30, rules longtext NOT NULL, revision bigint unsigned NOT NULL DEFAULT 1, PRIMARY KEY  (id)",
            'occurrences' => "created_by bigint unsigned NOT NULL DEFAULT 0, id bigint unsigned NOT NULL AUTO_INCREMENT, celebration_id bigint unsigned NOT NULL, title varchar(150) NOT NULL, location varchar(200) NOT NULL DEFAULT '', arrival datetime NOT NULL, starts datetime NOT NULL, ends datetime NOT NULL, notes text NOT NULL, rules_snapshot longtext NOT NULL, status varchar(20) NOT NULL DEFAULT 'published', PRIMARY KEY  (id), KEY celebration_id (celebration_id), KEY starts (starts)",
            'notifications' => "id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, type varchar(50) NOT NULL, title varchar(120) NOT NULL, body text NOT NULL, url text NOT NULL, occurrence_id bigint unsigned NOT NULL DEFAULT 0, event_id bigint unsigned NOT NULL DEFAULT 0, invite_id bigint unsigned NOT NULL DEFAULT 0, dedupe_key varchar(64) NOT NULL, created_at datetime NOT NULL, read_at datetime NULL, push_status varchar(20) NOT NULL DEFAULT 'pending', push_attempts int unsigned NOT NULL DEFAULT 0, push_next datetime NOT NULL, push_error varchar(100) NOT NULL DEFAULT '', PRIMARY KEY  (id), UNIQUE KEY dedupe_key (dedupe_key), KEY user_inbox (user_id,read_at), KEY push_queue (push_status,push_next)",
            'audit' => 'id bigint unsigned NOT NULL AUTO_INCREMENT, actor_id bigint unsigned NOT NULL, event_id bigint unsigned NOT NULL DEFAULT 0, action varchar(100) NOT NULL, detail text NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY event_id (event_id)',
        ];
        foreach ($schemas as $table => $schema) {
            $schema = str_replace(', ', ",\n", $schema);
            dbDelta('CREATE TABLE ' . self::table($table) . " (\n$schema\n) ENGINE=InnoDB $charset;");
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(self::table($table))))) { wp_die('Não foi possível criar as tabelas de escalas.'); }
            $columns=$wpdb->get_col('SHOW COLUMNS FROM '.self::table($table));
            foreach (explode(",\n",$schema) as $line) {
                if (preg_match('/^([a-z_]+) /',$line,$match) && !in_array($match[1],$columns,true)) { wp_die('Falha ao atualizar as tabelas das escalas. Verifique a permissão de alteração no banco e tente novamente.'); }
            }
        }
        // Multiple vacancies may be unassigned; preserve all existing slot IDs and history.
        if ($wpdb->get_var('SHOW INDEX FROM '.self::table('slots')." WHERE Key_name='original_event'")) {
            if (false===$wpdb->query('ALTER TABLE '.self::table('slots').' DROP INDEX original_event')) { wp_die('Não foi possível atualizar as vagas.'); }
        }
        foreach (['pastor'=>'Pastor', 'department'=>'Líder de departamento', 'team'=>'Líder de equipe', 'volunteer'=>'Voluntário', 'secretary'=>'Secretaria'] as $role=>$label) {
            add_role('ccs_'.$role, $label, $role==='volunteer'?['read'=>true]:['read'=>true,'ccs_access'=>true]);
            if ($role==='volunteer' && ($profile=get_role('ccs_volunteer'))) {
                foreach (array_keys($profile->capabilities) as $cap) { $profile->remove_cap($cap); }
                $profile->add_cap('read');
            }
        }
        if (!get_post((int)get_option('ccs_page_id'))) {
            $id = wp_insert_post(['post_title'=>'Escalas', 'post_name'=>'escalas', 'post_content'=>'[ccshalom_escalas]', 'post_type'=>'page', 'post_status'=>'publish'], true);
            if (is_wp_error($id)) { wp_die(esc_html($id->get_error_message())); }
            update_option('ccs_page_id', $id);
        }
        self::migrate_global_functions();
        self::migrate_event_groups();
        update_option('ccs_db_version', CCS_VERSION);
    }
    public static function url() { return get_permalink((int)get_option('ccs_page_id')) ?: home_url('/escalas/'); }
    public static function participant($id) {
        $u = get_userdata($id);
        return $u && self::has_profile($u) && get_user_meta($id, 'ccs_inactive', true) !== '1' && get_user_meta($id,'ccs_deleted',true)!=='1';
    }
    public static function secretary($id=null) {
        $u=get_userdata($id??get_current_user_id());
        return $u && in_array('ccs_secretary',$u->roles,true);
    }
    public static function can_serve($id) { return self::participant($id) && !self::secretary($id); }
    public static function management_access() {
        return self::secretary() || self::pastor() || in_array('ccs_department',wp_get_current_user()->roles,true);
    }
    public static function pastor() { return !self::secretary() && (current_user_can('manage_options') || in_array('ccs_pastor', wp_get_current_user()->roles, true)); }
    public static function manages_department($id) {
        global $wpdb;
        return !self::secretary() && (self::pastor() || (in_array('ccs_department',wp_get_current_user()->roles,true) && (int)$wpdb->get_var($wpdb->prepare('SELECT leader_id FROM '.self::table('departments').' WHERE id=%d', $id)) === get_current_user_id()));
    }
    public static function team($id) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('teams').' WHERE id=%d', $id), ARRAY_A); }
    public static function manages_team($id) { $t=self::team($id); return $t && self::manages_department($t['department_id']); }
    public static function sees_team($id) {
        global $wpdb;
        $t=self::team($id); if (!$t) { return false; }
        return self::secretary() || self::manages_department($t['department_id']) || (int)$t['leader_id'] === get_current_user_id() || (bool)$wpdb->get_var($wpdb->prepare('SELECT user_id FROM '.self::table('members').' WHERE team_id=%d AND user_id=%d', $id, get_current_user_id()));
    }
    public static function fail($message, $status=400) { throw new CCS_Exception($message, $status); }
    public static function require_that($test, $message='Você não tem permissão para esta ação.', $status=403) { if (!$test) { self::fail($message, $status); } }
    public static function routes() {
        self::passkey_routes();
        register_rest_route('ccshalom/v1','/notifications',['methods'=>'GET','permission_callback'=>[__CLASS__,'access'],'callback'=>[__CLASS__,'notification_feed']]);
        register_rest_route('ccshalom/v1', '/state', ['methods'=>'GET', 'permission_callback'=>[__CLASS__, 'access'], 'callback'=>[__CLASS__, 'state']]);
        register_rest_route('ccshalom/v1', '/action', ['methods'=>'POST', 'permission_callback'=>[__CLASS__, 'access'], 'callback'=>[__CLASS__, 'action']]);
    }
    public static function access() { return self::participant(get_current_user_id()) ?: new WP_Error('ccs_access', 'Entre com um usuário ativo do sistema de escalas.', ['status'=>403]); }
    public static function name($id) { $u=get_userdata($id); return $u ? $u->display_name : 'Usuário removido'; }
    public static function state() {
        global $wpdb;
        $uid=get_current_user_id();
        $teams=[]; $dept_ids=[];
        foreach ($wpdb->get_results('SELECT * FROM '.self::table('teams').' WHERE active=1 ORDER BY name', ARRAY_A) as $t) {
            $visible=self::sees_team($t['id']);$t['visible_to_me']=$visible;$t['is_department']=(bool)$t['is_department'];
            $t['can_functions']=self::leads_team($t['id']);
            $t['functions']=$visible?self::team_functions($t['id']):[];
            $t['can_manage']=self::manages_team($t['id']);
            $t['can_publish_open']=self::can_publish_team($t['id'],'open');
            $t['can_assume']=self::can_assume_team($t['id']);
            $t['members']=$visible?array_map('intval', $wpdb->get_col($wpdb->prepare('SELECT user_id FROM '.self::table('members').' WHERE team_id=%d', $t['id']))):[];
            $teams[]=$t; $dept_ids[]=(int)$t['department_id'];
        }
        $departments=$wpdb->get_results('SELECT * FROM '.self::table('departments').' WHERE active=1 ORDER BY name', ARRAY_A);
        foreach ($departments as &$d) { $d['can_manage']=self::manages_department($d['id']); } unset($d);
        $events=[]; $slots=[]; $invites=[];
        // Include history and future schedules. No contacts or private profile fields in the shared directory.
        foreach ($wpdb->get_results('SELECT * FROM '.self::table('events')." WHERE status<>'deleted' ORDER BY starts DESC", ARRAY_A) as $e) {
            $all=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('slots')." WHERE event_id=%d AND status<>'withdrawn'", $e['id']), ARRAY_A);
            $e['can_manage']=self::can_manage_event($e);
            $e['can_delegate']=$e['mode']==='functions' && self::leads_team($e['team_id']);
            $e['can_claim']=self::can_serve($uid) && in_array($e['mode'],['open','functions'],true) && self::is_member($e['team_id'],$uid);
            $team=self::team($e['team_id']); $e['team_name']=$team ? $team['name'] : 'Equipe';
            $e['department_id']=$team?(int)$team['department_id']:0;
            $e['department_name']=$wpdb->get_var($wpdb->prepare('SELECT name FROM '.self::table('departments').' WHERE id=%d',$e['department_id']))?:'Departamento';
            $events[]=$e;
            foreach ($all as $s) {
                $s['can_claim']=$e['can_claim'] && ($e['mode']!=='functions' || self::qualified($e['team_id'],$s['function_id'],$uid));
                $slots[]=$s;
                $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('invites').' WHERE slot_id=%d ORDER BY id DESC', $s['id']), ARRAY_A);
                foreach ($rows as $i) { if (self::secretary() || $e['can_manage'] || (int)$team['leader_id']===$uid || (int)$i['to_id']===$uid || (int)$i['from_id']===$uid) { $invites[]=$i; } }
            }
        }
        $users=[];
        foreach (get_users(['orderby'=>'display_name','order'=>'ASC']) as $u) {
            if (!self::has_profile($u)) { continue; }
            $row=['function_ids'=>array_map('intval',$wpdb->get_col($wpdb->prepare('SELECT q.function_id FROM '.self::table('qualifications').' q JOIN '.self::table('functions').' f ON f.id=q.function_id WHERE q.user_id=%d AND f.active=1',$u->ID))),'id'=>$u->ID,'name'=>$u->display_name,'active'=>self::participant($u->ID),'can_serve'=>self::can_serve($u->ID),'deleted'=>get_user_meta($u->ID,'ccs_deleted',true)==='1'];
            if (self::management_access()) { $row['phone']=get_user_meta($u->ID,'ccs_phone',true); $row['email']=$u->user_email; $row['roles']=$u->roles; }
            $users[]=$row;
        }
        $audit=[];
        foreach ($events as $event) {
            $team=self::team($event['team_id']);
            if (!self::secretary() && !$event['can_manage'] && (int)$team['leader_id']!==$uid) { continue; }
            $rows=$wpdb->get_results($wpdb->prepare('SELECT id,actor_id,event_id,action,created_at FROM '.self::table('audit').' WHERE event_id=%d ORDER BY id DESC LIMIT 50',$event['id']),ARRAY_A);
            foreach ($rows as $row) { $row['actor_name']=self::name($row['actor_id']); $row['created_at']=get_date_from_gmt($row['created_at']); $audit[]=$row; }
        }
        return array_merge(self::celebration_state($events), ['inbox'=>self::notification_feed(),'push'=>['available'=>self::push_available()],'notifications'=>self::notifications(),'functions'=>$wpdb->get_results('SELECT id,name FROM '.self::table('functions').' WHERE active=1 ORDER BY name',ARRAY_A),'can_catalog'=>self::can_catalog(),'passkeys'=>self::passkey_list(), 'passkey_available'=>self::passkey_available(), 'audit'=>$audit,'me'=>['id'=>$uid, 'name'=>self::name($uid), 'role_label'=>self::role_label(), 'pastor'=>self::pastor(),'secretary'=>self::secretary(),'can_manage'=>self::management_access(),'can_publish_event'=>self::can_publish_event(),'can_assign_functions'=>self::can_catalog(),'can_serve'=>self::can_serve($uid),'import_url'=>self::import_allowed()?self::import_url():'', 'phone'=>get_user_meta($uid,'ccs_phone',true), 'email'=>wp_get_current_user()->user_email], 'teams'=>$teams, 'departments'=>$departments, 'events'=>$events, 'slots'=>$slots, 'invites'=>$invites, 'users'=>$users, 'now'=>current_datetime()->format('Y-m-d H:i:s'), 'timezone'=>wp_timezone_string()]);
    }
    public static function insert($table, $data) { global $wpdb; if (false===$wpdb->insert(self::table($table),$data)) { self::fail('Não foi possível salvar. Tente novamente.',500); } return (int)$wpdb->insert_id; }
    public static function update($table,$data,$where) { global $wpdb; if (false===$wpdb->update(self::table($table),$data,$where)) { self::fail('Não foi possível atualizar. Tente novamente.',500); } }
    public static function audit($action,$event=0,$detail='') { return self::insert('audit',['actor_id'=>get_current_user_id(),'event_id'=>$event,'action'=>$action,'detail'=>$detail,'created_at'=>current_time('mysql',true)]); }
    public static function text($data,$key,$max=150) { $v=sanitize_text_field($data[$key]??''); self::require_that($v!=='' && mb_strlen($v)<=$max,'Preencha corretamente: '.$key.'.',400); return $v; }
    public static function ids($values) { return array_values(array_unique(array_filter(array_map('absint',(array)$values)))); }
    public static function datetime($value) {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',(string)$value,wp_timezone());
        self::require_that($d && $d->format('Y-m-d\TH:i')===$value,'Data ou horário inválido.',400);
        return $d;
    }
    public static function conflict($user,$arrival,$end,$except=0,$except_event=0) {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare('SELECT s.id FROM '.self::table('slots').' s JOIN '.self::table('events')." e ON e.id=s.event_id WHERE s.assigned_id=%d AND s.id<>%d AND e.id<>%d AND s.status IN ('pending','confirmed') AND e.status='published' AND e.arrival<%s AND e.ends>%s LIMIT 1",$user,$except,$except_event,$end,$arrival));
    }
    public static function slot($id) {
        global $wpdb;
        $s=$wpdb->get_row($wpdb->prepare('SELECT s.*,e.arrival,e.starts,e.ends,e.status event_status,e.team_id,e.mode,e.occurrence_id FROM '.self::table('slots').' s JOIN '.self::table('events').' e ON e.id=s.event_id WHERE s.id=%d',$id),ARRAY_A);
        self::require_that($s,'Convocação não encontrada.',404);
        self::require_that($s['status']!=='withdrawn','Esta vaga foi removida das necessidades desta data.',409);
        self::require_that($s['event_status']==='published' && $s['starts']>current_time('mysql'),'Esta escala foi cancelada ou já começou.',409);
        return $s;
    }
    public static function action($request) {
        global $wpdb;
        $a=$request->get_json_params(); if (!is_array($a)) { return new WP_Error('ccs_input','Dados inválidos.',['status'=>400]); }
        $lock='ccs_'.md5(DB_NAME.$wpdb->prefix);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock))!==1) { return new WP_Error('ccs_busy','Outra alteração está sendo salva. Tente novamente.',['status'=>409]); }
        $wpdb->query('START TRANSACTION');
        try {
            $result=self::mutate($a);
            if ($wpdb->query('COMMIT')===false) { self::fail('Não foi possível concluir a alteração.',500); }
            // Push campaigns are created only after the scheduling transaction commits.
            try { self::flush_push(); } catch (Throwable $e) { /* Durable notices remain pending for the next attempt. */ }
            return is_array($result) ? array_merge(['ok'=>true],$result) : ['ok'=>true,'message'=>$result?:'Alteração salva.'];
        } catch (CCS_Exception $e) { $wpdb->query('ROLLBACK'); return new WP_Error('ccs_action',$e->getMessage(),['status'=>$e->getCode()?:400]); }
        catch (Throwable $e) { $wpdb->query('ROLLBACK'); return new WP_Error('ccs_internal','Não foi possível concluir. Consulte o administrador.',['status'=>500]); }
        finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
    }
    private static function mutate($a) {
        global $wpdb;
        $uid=get_current_user_id(); $id=absint($a['id']??0);
        if (($a['action']??'')==='notice_read') { return self::read_notifications($a); }
        self::require_that(!self::secretary(),'O perfil Secretaria tem acesso somente para consulta.',403);
        switch ($a['action']??'') {
        case 'department':
            self::require_that(self::pastor());
            $leader=absint($a['leader_id']??0); self::require_that(!$leader || self::can_serve($leader),'Líder inválido.',400);
            $data=['name'=>self::text($a,'name'),'leader_id'=>$leader];
            if ($id) { self::require_that($wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('departments').' WHERE active=1 AND id=%d',$id)),'Departamento não encontrado.',404); self::update('departments',$data,['id'=>$id]); } else { $id=self::insert('departments',$data); }
            self::audit('department',0,(string)$id); break;
        case 'team':
            $department=absint($a['department_id']??0);
            self::require_that($department && $wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('departments').' WHERE active=1 AND id=%d',$department)),'Departamento inválido.',400);
            self::require_that(self::manages_department($department));
            if ($id) { self::require_that(self::manages_team($id)); self::require_active('teams',$id); }
            $leader=absint($a['leader_id']??0); self::require_that(!$leader || self::can_serve($leader),'Líder inválido.',400);
            $members=self::ids($a['members']??[]);
            foreach ($members as $m) { self::require_that(self::can_serve($m),'A equipe contém um usuário inativo.',400); }
            $mode=$a['mode']??'functions'; self::require_that(in_array($mode,['roster','open','functions'],true),'Modo de escala inválido.',400);
            $data=['name'=>self::text($a,'name'),'department_id'=>$department,'leader_id'=>$leader,'mode'=>$mode];
            if ($id) {
                $old=self::team($id); self::require_that((int)$old['department_id']===$department,'Não é permitido mudar o departamento de uma equipe existente.',400);
                self::update('teams',$data,['id'=>$id]);
            } else { $id=self::insert('teams',$data); }
            self::require_that(false!==$wpdb->delete(self::table('members'),['team_id'=>$id]),'Falha ao salvar integrantes.',500);
            foreach ($members as $m) { self::insert('members',['team_id'=>$id,'user_id'=>$m]); }
            if (array_key_exists('functions',$a)) { self::save_team_functions($id,$a['functions']); }
            self::audit('team',0,(string)$id); break;
        case 'user':
            self::require_that(self::pastor());
            $role=$a['role']??'ccs_volunteer';
            self::require_that(in_array($role,['ccs_pastor','ccs_department','ccs_team','ccs_volunteer','ccs_secretary'],true),'Perfil inválido.',400);
            if ($id) {
                self::require_that($id!==$uid,'Altere seu próprio perfil apenas pelo administrador do WordPress.',400);
                self::require_that(get_userdata($id) && !user_can($id,'manage_options'),'Este usuário deve ser gerenciado pelo WordPress.',400);
                self::require_that(self::has_profile(get_userdata($id)),'Usuário não pertence ao sistema de escalas.',400);
                self::require_that(get_user_meta($id,'ccs_deleted',true)!=='1','Este voluntário foi excluído das escalas.',409);
                if ($role==='ccs_secretary') {
                    self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT s.id FROM '.self::table('slots').' s JOIN '.self::table('events')." e ON e.id=s.event_id WHERE s.assigned_id=%d AND s.status<>'withdrawn' AND e.status='published' AND e.ends>%s",$id,current_time('mysql'))),'Antes de mudar para Secretaria, libere ou cancele as escalas futuras/em andamento desta pessoa.',409);
                    foreach (['departments','teams'] as $table) { self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table($table).' WHERE active=1 AND leader_id=%d',$id)),'Substitua esta pessoa nas lideranças antes de mudar para Secretaria.',409); }
                }
                $email=sanitize_email($a['email']??get_userdata($id)->user_email); $owner=email_exists($email);
                self::require_that(is_email($email) && (!$owner || (int)$owner===$id),'E-mail inválido ou já utilizado.',400);
                $r=wp_update_user(['ID'=>$id,'display_name'=>self::text($a,'name'),'user_email'=>$email,'role'=>$role]);
                if (is_wp_error($r)) { self::fail('Não foi possível atualizar o usuário.',400); }
                update_user_meta($id,'ccs_inactive',empty($a['active'])?'1':'0');
                if (empty($a['active'])) {
                    $future=$wpdb->get_results($wpdb->prepare('SELECT s.id,s.event_id,e.mode FROM '.self::table('slots').' s JOIN '.self::table('events')." e ON e.id=s.event_id WHERE s.assigned_id=%d AND e.status='published' AND e.starts>%s",$id,current_time('mysql')),ARRAY_A);
                    foreach ($future as $slot) {
                        self::update('slots',in_array($slot['mode'],['open','functions'],true)?['status'=>'available','assigned_id'=>0,'original_id'=>0]:['status'=>'declined'],['id'=>$slot['id']]);
                        self::audit('volunteer_deactivated',$slot['event_id'],'slot='.$slot['id']);
                    }
                    self::update('invites',['status'=>'cancelled'],['to_id'=>$id,'status'=>'pending']);
                    self::update('invites',['status'=>'cancelled'],['from_id'=>$id,'status'=>'pending']);
                }

            } else {
                $email=sanitize_email($a['email']??''); self::require_that(is_email($email) && !email_exists($email),'E-mail inválido ou já cadastrado. Para usuário existente, atribua um perfil de escalas pelo WordPress.',400);
                $name=self::text($a,'name');
                $id=wp_insert_user(['user_login'=>'ccs_'.strtolower(wp_generate_password(12,false,false)),'user_email'=>$email,'display_name'=>$name,'user_pass'=>wp_generate_password(24,true,true),'role'=>$role]);
                if (is_wp_error($id)) { self::fail('Não foi possível cadastrar o usuário.',400); }
            }
            if (array_key_exists('phone',$a)) { update_user_meta($id,'ccs_phone',sanitize_text_field($a['phone'])); }
            self::audit('user',0,(string)$id);
            return 'Usuário salvo. Para o primeiro acesso, use “Perdeu a senha?” com o e-mail cadastrado.';
        case 'schedule':
            return self::legacy_schedule($a);
        case 'edit_schedule':
            $event=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('events').' WHERE id=%d',$id),ARRAY_A);
            self::require_that($event && self::can_manage_event($event));
            self::require_that($event['status']==='published' && $event['starts']>current_time('mysql'),'Esta escala não pode mais ser editada.',409);
            
            $start=self::datetime($a['starts']??''); $end=self::datetime($a['ends']??''); $arrival=self::datetime($a['arrival']??'');
            self::require_that($arrival<=$start && $end>$start && $start>current_datetime(),'Confira as datas e os horários.',400);
            $data=['title'=>self::text($a,'title'),'location'=>sanitize_text_field($a['location']??''),'notes'=>sanitize_textarea_field($a['notes']??''),'starts'=>$start->format('Y-m-d H:i:s'),'ends'=>$end->format('Y-m-d H:i:s'),'arrival'=>$arrival->format('Y-m-d H:i:s')];
            $changed=$data['starts']!==$event['starts'] || $data['ends']!==$event['ends'] || $data['arrival']!==$event['arrival'];
            self::require_that(!$changed || !(int)$event['occurrence_id'],'Altere os horários em Editar esta data, na celebração, para atualizar todas as equipes juntas.',409);
            if ((int)$event['occurrence_id']) { self::require_that($data['title']===$event['title'] && $data['location']===$event['location'],'O nome e local pertencem ao evento. Edite apenas as orientações da sua equipe.',403); }
            if ($changed) {
                $slots=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('slots')." WHERE event_id=%d AND status<>'withdrawn'",$id),ARRAY_A);
                foreach ($slots as $slot) {
                    if (in_array($event['mode'],['open','functions'],true)) {
                        self::update('slots',['assigned_id'=>0,'original_id'=>0,'status'=>'available'],['id'=>$slot['id']]);
                        self::update('invites',['status'=>'cancelled'],['slot_id'=>$slot['id'],'status'=>'pending']);
                        continue;
                    }
                    $active=self::can_serve($slot['original_id']);
                    if ($active) { self::require_that(!self::conflict($slot['original_id'],$data['arrival'],$data['ends'],0,$id),'Conflito de horário: '.self::name($slot['original_id']),409); }
                    self::update('slots',['assigned_id'=>$slot['original_id'],'status'=>$active?'pending':'declined'],['id'=>$slot['id']]);
                    self::update('invites',['status'=>'cancelled'],['slot_id'=>$slot['id'],'status'=>'pending']);
                }
            }
            self::update('events',$data,['id'=>$id]);
            $audit=self::audit('schedule_edited',$id,wp_json_encode(['before'=>$event,'after'=>$data,'reset'=>$changed]));
            self::notify_people(self::team_recipients($event['team_id']),'team_updated','Orientações da sua equipe atualizadas',$event['title'].'. Confira as orientações de '.self::team($event['team_id'])['name'].'.',$event['occurrence_id'],$id,'team-edit:'.$audit);
            if ($changed && in_array($event['mode'],['open','functions'],true)) { return 'Horários alterados. A vaga foi reaberta para uma nova escolha.'; }
            return $changed?'Horários alterados. Os integrantes originais precisam confirmar novamente; substituições anteriores não valem para o novo horário.':'Escala atualizada.';
        case 'cancel':
            $e=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('events').' WHERE id=%d',$id),ARRAY_A);
            self::require_that($e && self::can_manage_event($e));
            self::require_that($e['status']!=='deleted','Esta escala já foi excluída.',409);
            self::require_that($e['status']==='published' && $e['starts']>current_time('mysql'),'Esta participação não pode mais ser cancelada.',409);
            self::cancel_event($id); break;
        case 'respond':
            $s=self::slot($id); self::require_that((int)$s['assigned_id']===$uid);
            $status=$a['status']??''; self::require_that(in_array($status,['confirmed','declined'],true),'Resposta inválida.',400);
            if ($status==='confirmed') { self::require_that(!self::conflict($uid,$s['arrival'],$s['ends'],$id),'Você já tem outra escala neste horário.',409); }
            self::update('slots',['status'=>$status],['id'=>$id]);
            self::update('invites',['status'=>'cancelled'],['slot_id'=>$id,'status'=>'pending']);
            $audit=self::audit('response_'.$status,$s['event_id'],'slot='.$id);
            if ($status==='declined') { self::notify_people(self::team_recipients($s['team_id'],false),'participation_declined','Participação aguardando substituto',self::name($uid).' não poderá participar de '.self::event_title($s['event_id']).'.',$s['occurrence_id'],$s['event_id'],'decline:'.$audit); }
            break;
        case 'invite':
            $s=self::slot($id); self::require_that((int)$s['assigned_id']===$uid && $s['status']==='declined');
            $to=absint($a['to_id']??0); self::require_that($to!==$uid && self::can_serve($to),'Selecione outro voluntário ativo.',400);
            if ((int)$s['function_id']) { self::require_that(self::has_function($s['function_id'],$to),'Escolha um voluntário da mesma função.',400); }
            self::require_that(!self::conflict($to,$s['arrival'],$s['ends']),'Este voluntário já está escalado nesse horário.',409);
            self::update('invites',['status'=>'cancelled'],['slot_id'=>$id,'status'=>'pending']);
            $invite=self::insert('invites',['slot_id'=>$id,'from_id'=>$uid,'to_id'=>$to,'status'=>'pending','created_at'=>current_time('mysql',true)]);
            self::audit('replacement_invited',$s['event_id'],'slot='.$id.';to='.$to);
            self::notify_people([$to],'replacement_requested','Pedido de troca de escala',self::name($uid).' convidou você para '.($s['function_name']?:'servir').' em '.self::event_title($s['event_id']).'. Abra o evento para aceitar ou recusar.',$s['occurrence_id'],$s['event_id'],'invite:'.$invite,$invite);
            return 'Convite registrado. A troca depende do aceite do convidado no painel.';
        case 'answer_invite':
            $i=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('invites').' WHERE id=%d',$id),ARRAY_A);
            self::require_that($i && (int)$i['to_id']===$uid); self::require_that($i['status']==='pending','Este convite já foi respondido ou cancelado.',409);
            $s=self::slot($i['slot_id']);
            self::require_that($s['status']==='declined' && (int)$s['assigned_id']===(int)$i['from_id'],'Esta vaga já foi alterada.',409);
            $accept=($a['accept']??false)===true;
            if ($accept) {
                if ((int)$s['function_id']) { self::require_that(self::has_function($s['function_id'],$uid),'Você precisa ter esta função no seu perfil para aceitar.',409); }
                self::require_that(!self::conflict($uid,$s['arrival'],$s['ends'],$s['id']),'Você já está escalado neste horário.',409);
                self::update('slots',['assigned_id'=>$uid,'status'=>'confirmed'],['id'=>$s['id']]);
            }
            self::update('invites',['status'=>$accept?'accepted':'declined'],['id'=>$id]);
            $audit=self::audit($accept?'replacement_accepted':'replacement_declined',$s['event_id'],'invite='.$id);
            self::notify_people(array_merge([(int)$i['from_id']],self::team_recipients($s['team_id'],false)),$accept?'replacement_accepted':'replacement_declined',$accept?'Troca de escala confirmada':'Pedido de troca recusado',self::name($uid).($accept?' aceitou':' recusou').' a substituição em '.self::event_title($s['event_id']).'.',$s['occurrence_id'],$s['event_id'],'answer:'.$audit,$id);
            return $accept?'Substituição confirmada. Esta escala agora está com você.':'Convite recusado. A vaga continua aguardando substituto.';
        default: return self::management_action($a);
        }
        return 'Alteração salva.';
    }
    public static function menu() {
        add_menu_page('CCShalom Escalas','Escalas','read','ccs-escalas',[__CLASS__,'admin_page'],'dashicons-calendar-alt',26);
        add_submenu_page('ccs-escalas','Importar pessoas','Importar pessoas','manage_options','ccs-import',[__CLASS__,'import_page']);
    }
    public static function admin_page() {
        if (!self::participant(get_current_user_id())) { wp_die('Seu usuário não tem acesso às escalas.'); }
        echo '<div class="wrap"><h1>CCShalom • Escalas</h1><p><a class="button button-primary" href="'.esc_url(self::url()).'">Abrir painel de escalas</a></p>';
        if (current_user_can('manage_options')) {
            echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=ccs-updates')).'">Atualizações via GitHub</a></p><h2>Configurações</h2><p>Defina o fuso horário em Configurações → Geral. Os horários das escalas usam esse fuso.</p><form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="ccs_settings">';
            wp_nonce_field('ccs_settings');
            echo '<table class="form-table"><tr><th><label for="ccs-logo">URL do logo oficial</label></th><td><input id="ccs-logo" class="regular-text" type="url" name="logo" value="'.esc_attr(get_option('ccs_logo','')).'"><p>Envie o logo branco em Mídia e cole aqui a URL HTTPS. Sem imagem configurada, será exibido o nome CCShalom em texto.</p></td></tr><tr><th>Login personalizado</th><td><label><input type="checkbox" name="login" value="1" '.checked(get_option('ccs_login','1'),'1',false).'> Aplicar identidade da igreja ao login do WordPress</label></td></tr></table>';
            submit_button('Salvar configurações'); echo '</form><p>Usuários existentes: em Usuários → Editar, atribua um dos perfis de escalas. Vincule os líderes aos respectivos departamentos e equipes no painel.</p>';
        }
        echo '</div>';
    }
    public static function settings() {
        if (!current_user_can('manage_options')) { wp_die('Sem permissão.'); }
        check_admin_referer('ccs_settings');
        $logo=esc_url_raw(wp_unslash($_POST['logo']??''),['https']);
        update_option('ccs_logo',$logo); update_option('ccs_login',isset($_POST['login'])?'1':'0'); delete_transient('ccs_release');
        wp_safe_redirect(admin_url('admin.php?page=ccs-escalas')); exit;
    }
    public static function assets() {
        wp_enqueue_style('ccs-font',plugins_url('assets/font.css',CCS_FILE),[],CCS_VERSION);
        wp_enqueue_style('ccs-app',plugins_url('assets/app.css',CCS_FILE),[],CCS_VERSION);
        self::auth_assets();
        if (is_user_logged_in() && self::participant(get_current_user_id())) {
            self::push_assets();
            wp_enqueue_script('ccs-event-views',plugins_url('assets/event-views.js',CCS_FILE),[],CCS_VERSION,true);
            wp_enqueue_script('ccs-app',plugins_url('assets/app.js',CCS_FILE),['ccs-auth','ccs-event-views'],CCS_VERSION,true);
            wp_localize_script('ccs-app','CCS_CONFIG',['api'=>rest_url('ccshalom/v1/'),'nonce'=>wp_create_nonce('wp_rest'),'login'=>self::auth_url(), 'logout'=>self::logout_url(),'lost'=>self::auth_url('forgot')]);
        }
    }
    public static function page() {
        global $post;
        if (!is_page((int)get_option('ccs_page_id')) && !($post && has_shortcode($post->post_content,'ccshalom_escalas'))) { return; }
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE',true); }
        nocache_headers();
        if (($_REQUEST['action']??'')==='ccs_auth') { self::auth_submit(); exit; }
        if (($_SERVER['REQUEST_METHOD']??'')==='POST' && ($_POST['action']??'')==='ccs_register') { self::register_account(); exit; }
        if (isset($_GET['ccs_auth']) || (!is_user_logged_in() && !isset($_GET['ccs_register']))) { self::auth_page(); exit; }
        if (isset($_GET['ccs_register'])) { self::registration_page(); exit; }
        if (!self::participant(get_current_user_id())) { status_header(403); }
        show_admin_bar(false);
        self::assets();
        include CCS_DIR.'templates/app.php'; exit;
    }
    public static function shortcode() { self::assets(); return self::shell(); }
    public static function shell() {
        $logo=get_option('ccs_logo','');
        $brand=$logo ? '<img class="ccs-logo" src="'.esc_url($logo).'" alt="CCShalom">' : '<span class="ccs-wordmark">CCShalom</span>';
        $html='<div id="ccs-app"><header class="ccs-header"><div class="ccs-top">'.$brand.'<div class="ccs-header-actions"><span class="ccs-tag">ESCALAS</span><button type="button" class="ccs-bell" data-action="notifications" aria-label="Notificações"><svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span class="ccs-bell-count" hidden></span></button></div></div><p class="ccs-motto">COMUNHÃO · DISCIPULADO · MISSÃO</p></header>';
        if (!is_user_logged_in()) {
            return $html.'<main class="ccs-main"><section class="ccs-card ccs-welcome"><span class="ccs-eyebrow">BEM-VINDO À EQUIPE</span><h1>Servimos melhor<br>quando estamos juntos.</h1><p>Acesse suas escalas e confirme sua participação nas próximas celebrações.</p><a class="ccs-button" href="'.esc_url(wp_login_url(self::url())).'">Entrar na minha conta</a></section></main></div>';
        }
        if (!self::participant(get_current_user_id())) { return $html.'<main class="ccs-main"><section class="ccs-card"><h1>Acesso às escalas</h1><p>Peça à liderança para ativar seu cadastro e vincular seu usuário à equipe.</p><a href="'.esc_url(self::logout_url()).'">Sair</a></section></main></div>'; }
        return $html.'<div id="ccs-message" role="status" aria-live="polite"></div><main class="ccs-main" id="ccs-content"><p class="ccs-card">Carregando suas escalas…</p></main><nav class="ccs-nav" aria-label="Navegação principal"></nav><dialog id="ccs-dialog" aria-labelledby="ccs-dialog-title"></dialog><noscript>Ative o JavaScript para usar o painel de escalas.</noscript></div>'.self::push_panel();
    }
    public static function login_style() {
        if (get_option('ccs_login','1')!=='1') { return; }
        wp_enqueue_style('ccs-font',plugins_url('assets/font.css',CCS_FILE),[],CCS_VERSION);
        wp_enqueue_style('ccs-login',plugins_url('assets/login.css',CCS_FILE),[],CCS_VERSION);
        $logo=get_option('ccs_logo','');
        if ($logo) { wp_add_inline_style('ccs-login','body.login h1 a{background-image:url('.wp_json_encode(esc_url_raw($logo)).');font-size:0;}'); }
    }
}
final class CCS_Exception extends RuntimeException {}
require_once CCS_DIR.'includes/updater.php';

