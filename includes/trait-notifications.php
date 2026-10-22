<?php
if (!defined('ABSPATH')) { exit; }
trait CCS_Notifications {
    public static function event_title($id) { global $wpdb;return (string)$wpdb->get_var($wpdb->prepare('SELECT title FROM '.self::table('events').' WHERE id=%d',$id)); }
    public static function notification_feed() {
        global $wpdb;
        $items=$wpdb->get_results($wpdb->prepare('SELECT id,type,title,body,url,occurrence_id,event_id,invite_id,created_at,read_at FROM '.self::table('notifications').' WHERE user_id=%d ORDER BY id DESC LIMIT 100',get_current_user_id()),ARRAY_A);
        foreach ($items as &$item) { $item['created_at']=get_date_from_gmt($item['created_at']); }unset($item);
        $unread=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::table('notifications')." WHERE user_id=%d AND read_at IS NULL AND type<>'replacement_requested'",get_current_user_id()));
        return ['requests'=>self::notifications(),'items'=>$items,'unread'=>$unread];
    }
    private static function read_notifications($a) {
        global $wpdb;
        if (($a['all']??false)===true) {
            self::require_that(false!==$wpdb->query($wpdb->prepare('UPDATE '.self::table('notifications').' SET read_at=%s WHERE user_id=%d AND read_at IS NULL',current_time('mysql',true),get_current_user_id())),'Não foi possível atualizar os avisos.',500);
            return 'Seus avisos foram marcados como lidos.';
        }
        $ids=self::ids($a['ids']??[]);
        self::require_that($ids && count($ids)<=100,'Selecione os avisos que deseja marcar como lidos.',400);
        $list=implode(',',array_fill(0,count($ids),'%d'));
        $owned=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::table('notifications').' WHERE user_id=%d AND id IN ('.$list.')',array_merge([get_current_user_id()],$ids)));
        self::require_that($owned===count($ids),'Você só pode marcar os seus próprios avisos.',403);
        self::require_that(false!==$wpdb->query($wpdb->prepare('UPDATE '.self::table('notifications').' SET read_at=%s WHERE user_id=%d AND id IN ('.$list.') AND read_at IS NULL',array_merge([current_time('mysql',true),get_current_user_id()],$ids))),'Não foi possível atualizar os avisos.',500);
        return 'Avisos marcados como lidos.';
    }
    public static function notify_people($ids,$type,$title,$body,$occurrence=0,$event=0,$dedupe='',$invite=0) {
        global $wpdb;
        $url=add_query_arg('ccs_event',(int)$occurrence,self::url());
        foreach (self::ids($ids) as $uid) {
            if (!self::participant($uid) || $uid===get_current_user_id()) { continue; }
            $key=hash('sha256',$type.'|'.$dedupe.'|'.$uid);
            if ($wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('notifications').' WHERE dedupe_key=%s',$key))) { continue; }
            self::insert('notifications',['user_id'=>$uid,'type'=>sanitize_key($type),'title'=>mb_substr(sanitize_text_field($title),0,120),'body'=>mb_substr(sanitize_text_field($body),0,500),'url'=>$url,'occurrence_id'=>(int)$occurrence,'event_id'=>(int)$event,'invite_id'=>(int)$invite,'dedupe_key'=>$key,'created_at'=>current_time('mysql',true),'push_status'=>'pending','push_attempts'=>0,'push_next'=>current_time('mysql',true),'push_error'=>'']);
        }
    }
    public static function team_recipients($team,$members=true) {
        global $wpdb;$t=self::team($team);if (!$t) { return []; }
        $ids=[$t['leader_id']];
        if ($members) { $ids=array_merge($ids,$wpdb->get_col($wpdb->prepare('SELECT user_id FROM '.self::table('members').' WHERE team_id=%d',$team))); }
        return self::ids($ids);
    }
    public static function department_recipients($department) {
        global $wpdb;
        $ids=$wpdb->get_col($wpdb->prepare('SELECT leader_id FROM '.self::table('departments').' WHERE id=%d',$department));
        return self::ids(array_merge($ids,$wpdb->get_col($wpdb->prepare('SELECT leader_id FROM '.self::table('teams').' WHERE department_id=%d AND active=1',$department))));
    }
    public static function notify_department($department,$type,$title,$body,$occurrence,$event,$dedupe) {
        global $wpdb;
        $ids=$wpdb->get_col($wpdb->prepare('SELECT leader_id FROM '.self::table('departments').' WHERE id=%d',$department));
        $creator=$wpdb->get_var($wpdb->prepare('SELECT created_by FROM '.self::table('occurrences').' WHERE id=%d',$occurrence));$ids[]=$creator;
        self::notify_people($ids,$type,$title,$body,$occurrence,$event,$dedupe);
    }
    public static function notify_event_published($id) {
        $o=self::occurrence($id);$ids=[];
        foreach ($o['rules_snapshot'] as $rule) { if (count(self::occurrence_team_events($id,$rule['department_id']))<self::requirement_count($rule)) { $ids=array_merge($ids,self::department_recipients($rule['department_id'])); } }
        self::notify_people($ids,'event_published','Evento disponível para sua equipe',$o['title'].' · '.mysql2date('d/m/Y H:i',$o['starts']).'. Assuma a participação da sua equipe no evento.',$id,0,'published:'.$id);
    }
    public static function notify_team_publication($event,$audit) {
        global $wpdb;
        $e=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('events').' WHERE id=%d',$event),ARRAY_A);if (!$e) { return; }
        $team=self::team($e['team_id']);$title=$e['mode']==='roster'?'Você foi convocado':'Vagas disponíveis na sua equipe';
        self::notify_people(self::team_recipients($e['team_id']),'team_published',$title,$team['name'].' · '.$e['title'].($e['mode']==='roster'?'. Confirme sua participação.':'. Veja as vagas e escolha como servir.'),$e['occurrence_id'],$event,'team:'.$audit);
    }
    public static function notify_occurrence_changed($id,$type,$audit) {
        global $wpdb;$o=self::occurrence($id);$ids=[$o['created_by']];
        foreach ($o['rules_snapshot'] as $r) { $ids=array_merge($ids,self::department_recipients($r['department_id'])); }
        foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('events')." WHERE occurrence_id=%d AND status<>'deleted'",$id),ARRAY_A) as $event) {
            $ids=array_merge($ids,self::team_recipients($event['team_id']),$wpdb->get_col($wpdb->prepare('SELECT assigned_id FROM '.self::table('slots').' WHERE event_id=%d',$event['id'])));
        }
        self::notify_people($ids,$type,$type==='event_cancelled'?'Evento cancelado':'Evento atualizado',$o['title'].($type==='event_cancelled'?'. A participação das equipes foi cancelada.':'. Confira a data, os horários e sua participação.'),$id,0,'occurrence:'.$audit);
    }
    public static function push_available() {
        return function_exists('portal_push_send') && class_exists('PauloMaeda\\PortalPush\\Campaigns') && defined('PPUSH_VERSION') && version_compare(PPUSH_VERSION,'0.4.0','>=');
    }
    public static function push_boot() {
        add_action('ccs_flush_push',[__CLASS__,'flush_push']);
        add_action('init',[__CLASS__,'schedule_push'],30);
    }
    public static function schedule_push() {
        if (!self::push_available() || wp_next_scheduled('ccs_flush_push')) { return; }
        global $wpdb;
        if ($wpdb->get_var('SELECT id FROM '.self::table('notifications')." WHERE push_status IN ('pending','retry') AND created_at>UTC_TIMESTAMP()-INTERVAL 7 DAY LIMIT 1")) {
            wp_schedule_single_event(time()+30,'ccs_flush_push');
        }
    }
    public static function flush_push() {
        if (!self::push_available()) { return; }
        global $wpdb;
        $rows=$wpdb->get_results('SELECT * FROM '.self::table('notifications')." WHERE push_status IN ('pending','retry') AND push_attempts<10 AND push_next<=UTC_TIMESTAMP() AND created_at>UTC_TIMESTAMP()-INTERVAL 7 DAY ORDER BY id LIMIT 25",ARRAY_A);
        foreach ($rows as $row) {
            if (!self::participant($row['user_id'])) { $wpdb->update(self::table('notifications'),['push_status'=>'skipped'],['id'=>$row['id']]);continue; }
            $error='';
            try {
                $result=portal_push_send(['title'=>mb_substr($row['title'],0,80),'message'=>mb_strlen($row['body'])>300?mb_substr($row['body'],0,299).'…':$row['body'],'url'=>$row['url'],'audience_type'=>'people','audience_ids'=>[(int)$row['user_id']],'idempotency_key'=>'ccs:notice:'.$row['dedupe_key']]);
                if (is_wp_error($result)) { $error=sanitize_key($result->get_error_code());throw new RuntimeException('Portal Push indisponível.'); }
                if (!is_int($result) || $result<1) { $error='ppush_invalid_result';throw new RuntimeException('Resposta inválida.'); }
                $wpdb->update(self::table('notifications'),['push_status'=>'queued','push_error'=>'','push_attempts'=>(int)$row['push_attempts']+1],['id'=>$row['id']]);
            } catch (Throwable $e) {
                $attempt=(int)$row['push_attempts']+1;
                $wpdb->update(self::table('notifications'),['push_status'=>$attempt>=10?'failed':'retry','push_attempts'=>$attempt,'push_next'=>gmdate('Y-m-d H:i:s',time()+min(3600,60*(2**min($attempt,6)))),'push_error'=>mb_substr($error?:'ppush_exception',0,100)],['id'=>$row['id']]);
            }
        }
        self::schedule_push();
    }
    public static function push_assets() {
        if (!self::push_available() || !class_exists('PauloMaeda\\PortalPush\\Frontend') || !shortcode_exists('portal_push')) { return; }
        wp_enqueue_style('portal-push',PPUSH_URL.'assets/portal.css',[],PPUSH_VERSION);
        remove_action('wp_footer',['PauloMaeda\\PortalPush\\Frontend','prompt']);
        if (!\PauloMaeda\PortalPush\Core::enabled(get_current_user_id())) { return; }
        $keys=\PauloMaeda\PortalPush\Transport::keys();
        wp_enqueue_script('portal-push',PPUSH_URL.'assets/portal.js',[],PPUSH_VERSION,true);
        wp_localize_script('portal-push','PortalPush',['api'=>rest_url('portal-push/v1/'),'nonce'=>wp_create_nonce('wp_rest'),'publicKey'=>is_wp_error($keys)?'':$keys['publicKey'],'worker'=>add_query_arg('portal_push_worker','1',home_url('/')),'scope'=>home_url('/'),'pwa'=>\PauloMaeda\PortalPush\Pwa::provider(),'storage'=>'ppush-'.substr(md5(home_url('/')),0,12)]);
    }
    public static function push_panel() {
        return self::push_available() && shortcode_exists('portal_push')?'<div id="ccs-push-parking" hidden><div id="ccs-push-panel">'.do_shortcode('[portal_push]').'</div></div>':'';
    }
}
