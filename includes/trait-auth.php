<?php
if (!defined('ABSPATH')) { exit; }
trait CCS_Auth {
    public static function auth_boot() {
        foreach (['admin_post_nopriv_ccs_auth','admin_post_ccs_auth'] as $hook) { add_action($hook,[__CLASS__,'auth_submit']); }
        add_filter('authenticate',function($user) {
            if (($_POST['action']??'')==='ccs_auth' && $user instanceof WP_User && !self::participant($user->ID)) { return new WP_Error('ccs_inactive','Seu cadastro não tem acesso ativo às escalas. Procure a liderança.'); }
            return $user;
        },99);
        add_action('login_init',function() {
            // Preserve WordPress administration/recovery; intercept only links targeting this app.
            if ($_SERVER['REQUEST_METHOD']==='GET' && !isset($_GET['action']) && isset($_GET['redirect_to']) && wp_unslash($_GET['redirect_to'])===self::url()) { wp_safe_redirect(self::auth_url()); exit; }
        });
        add_action('after_password_reset',function($user) { self::revoke_all_keys($user->ID); });
    }
    public static function role_label() {
        if (self::secretary()) { return 'Secretaria'; }
        if (self::pastor()) { return current_user_can('manage_options')?'Administrador':'Pastor'; }
        global $wpdb;$uid=get_current_user_id();
        if ($wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('departments').' WHERE active=1 AND leader_id=%d',$uid))) { return 'Líder de departamento'; }
        if ($wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('teams').' WHERE active=1 AND leader_id=%d',$uid))) { return 'Líder de equipe'; }
        return 'Voluntário';
    }
    public static function auth_url($screen='login') { return add_query_arg('ccs_auth',$screen,self::url()); }
    public static function logout_url() { return wp_nonce_url(add_query_arg(['action'=>'ccs_auth','screen'=>'logout'],self::url()),'ccs_logout'); }
    public static function auth_binding($create=false) {
        $value=$_COOKIE['ccs_device_flow']??'';
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D',$value)) {
            if (!$create || headers_sent()) { return ''; }
            $value=bin2hex(random_bytes(32));
            setcookie('ccs_device_flow',$value,['expires'=>0,'path'=>'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
            $_COOKIE['ccs_device_flow']=$value;
        }
        return hash_hmac('sha256',$value,wp_salt('auth'));
    }
    public static function auth_assets() {
        wp_enqueue_style('ccs-account',plugins_url('assets/account.css',CCS_FILE),['ccs-app'],CCS_VERSION);
        wp_enqueue_script('ccs-auth',plugins_url('assets/auth.js',CCS_FILE),[],CCS_VERSION,true);
        wp_localize_script('ccs-auth','CCS_AUTH',['api'=>rest_url('ccshalom/v1/'),'nonce'=>is_user_logged_in()?wp_create_nonce('wp_rest'):'','home'=>self::url(),'login'=>self::auth_url(),'enabled'=>self::passkey_available()]);
        self::auth_binding(true);
    }
    public static function auth_page($error='',$screen=null) {
        if ($screen===null) { $screen=sanitize_key($_GET['ccs_auth']??'login'); }
        if (!in_array($screen,['login','forgot','reset'],true)) { $screen='login'; }
        if (is_user_logged_in() && $screen==='login') { wp_safe_redirect(self::url()); exit; }
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE',true); }
        nocache_headers(); header('Referrer-Policy: no-referrer'); show_admin_bar(false); self::assets();
        include CCS_DIR.'templates/login.php';
    }
    public static function auth_limit($scope,$max=20) {
        $key='ccs_limit_'.hash_hmac('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR']??''),wp_salt('nonce'));
        $n=(int)get_transient($key);self::require_that($n<$max,'Muitas tentativas. Aguarde alguns minutos e tente novamente.',429);
        set_transient($key,$n+1,10*MINUTE_IN_SECONDS);
    }
    public static function auth_submit() {
        nocache_headers();$screen=sanitize_key($_REQUEST['screen']??'login');
        if ($screen==='logout') { check_admin_referer('ccs_logout');wp_logout();wp_safe_redirect(self::auth_url());exit; }
        try {
            self::require_that(($_SERVER['REQUEST_METHOD']??'')==='POST','Método inválido.',405);
            $a=wp_unslash($_POST);
            self::require_that(wp_verify_nonce($a['_wpnonce']??'','ccs_auth') && self::auth_binding() && is_string($a['binding']??null) && hash_equals(self::auth_binding(),$a['binding']),'O formulário expirou. Recarregue e tente novamente.',403);
            self::auth_limit('password');
            if ($screen==='login') {
                $u=wp_signon(['user_login'=>sanitize_text_field($a['login']??''),'user_password'=>is_string($a['password']??null)?$a['password']:'','remember'=>!empty($a['remember'])],is_ssl());
                if (is_wp_error($u)) { self::fail('Não foi possível entrar. Confira seu e-mail e senha ou procure a liderança se o cadastro estiver inativo.',403); }
                if (!self::participant($u->ID)) { wp_clear_auth_cookie();self::fail('Seu cadastro não tem acesso ativo às escalas.',403); }
                wp_safe_redirect(self::url());exit;
            }
            if ($screen==='forgot') {
                add_filter('retrieve_password_message',function($message,$key,$login) {
                    return "Para redefinir sua senha da CCShalom, acesse:\n".add_query_arg(['key'=>$key,'login'=>$login],self::auth_url('reset'))."\n\nSe você não solicitou a alteração, ignore esta mensagem.\n";
                },10,3);
                retrieve_password(sanitize_text_field($a['login']??''));
                wp_safe_redirect(add_query_arg('sent','1',self::auth_url('forgot')));exit;
            }
            self::require_that($screen==='reset','Ação inválida.',400);
            $user=check_password_reset_key($a['key']??'',$a['login']??'');
            self::require_that(!is_wp_error($user),'Este link expirou. Solicite uma nova recuperação de senha.',400);
            $password=$a['password']??'';
            self::require_that(is_string($password) && strlen($password)>=12 && strlen($password)<=128 && trim($password)===$password,'Use de 12 a 128 caracteres, sem espaços nas extremidades.',400);
            reset_password($user,$password);
            wp_safe_redirect(add_query_arg('reset','1',self::auth_url()));exit;
        } catch (CCS_Exception $e) { status_header($e->getCode()?:400); self::auth_page($e->getMessage(),$screen);exit; }
    }
}
