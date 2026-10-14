<?php
if (!defined('ABSPATH')) { exit; }
trait CCS_Registration {
    public static function has_profile($user) {
        return $user && (user_can($user,'manage_options') || (bool)array_intersect($user->roles,['ccs_pastor','ccs_department','ccs_team','ccs_volunteer','ccs_secretary']));
    }
    public static function signup_url() { return add_query_arg('ccs_register','1',self::url()); }
    public static function signup_link() {
        echo '<p class="ccs-signup-link">Ainda não tem conta? <a href="'.esc_url(self::signup_url()).'">Cadastre-se como voluntário</a></p>';
    }
    public static function registration_message($message) {
        return isset($_GET['ccs_registered']) ? $message.'<p class="message">Cadastro realizado! Entre com seu e-mail e senha. A liderança poderá vincular você às equipes e funções.</p>' : $message;
    }
    public static function create_volunteer($data) {
        $name=self::text($data,'name');
        $email=sanitize_email($data['email']??'');
        self::require_that(is_email($email),'Informe um e-mail válido.',400);
        self::require_that(!email_exists($email),'Este e-mail já está cadastrado. Entre ou use “Perdeu a senha?”.',409);
        $phone=preg_replace('/[^0-9]/','',(string)($data['phone']??''));
        self::require_that(strlen($phone)>=10 && strlen($phone)<=15,'Informe o telefone com DDD (e código do país, se necessário).',400);
        $password=$data['password']??'';
        self::require_that(is_string($password) && strlen($password)>=12 && strlen($password)<=128 && trim($password)===$password,'Use uma senha entre 12 e 128 caracteres, sem espaços nas extremidades.',400);
        // The public form never accepts roles, capabilities, active flags or team assignments.
        $id=wp_insert_user(['user_login'=>'ccs_'.strtolower(wp_generate_password(24,false,false)),'user_email'=>$email,'display_name'=>$name,'user_pass'=>$password,'role'=>'ccs_volunteer']);
        if (is_wp_error($id)) { self::fail('Não foi possível criar a conta. Confira os dados e tente novamente.',400); }
        update_user_meta($id,'ccs_phone',$phone);
        return $id;
    }
    public static function register_account() {
        nocache_headers();
        if (is_user_logged_in()) { wp_safe_redirect(self::url()); exit; }
        try {
            self::require_that(($_SERVER['REQUEST_METHOD']??'')==='POST','Método inválido.',405);
            $data=wp_unslash($_POST);
            if (isset($data['ccs_name'])) { $data['name']=$data['ccs_name']; }
            self::require_that(is_string($data['_wpnonce']??null) && wp_verify_nonce($data['_wpnonce'],'ccs_register'),'O formulário expirou. Recarregue a página e tente novamente.',403);
            self::require_that(empty($data['website']),'Não foi possível enviar o cadastro.',400);
            // Use only the direct peer address, never an untrusted forwarded header.
            $key='ccs_reg_'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'unknown',wp_salt('nonce'));
            $attempts=(int)get_transient($key);
            self::require_that($attempts<20,'Muitas tentativas de cadastro. Aguarde uma hora e tente novamente.',429);
            set_transient($key,$attempts+1,HOUR_IN_SECONDS);
            self::create_volunteer($data);
            wp_safe_redirect(add_query_arg('ccs_registered','1',self::auth_url())); exit;
        } catch (CCS_Exception $error) {
            status_header($error->getCode()?:400);
            self::registration_page($error->getMessage()); exit;
        } catch (Throwable $error) {
            status_header(400); self::registration_page('Confira os campos e tente novamente.'); exit;
        }
    }
    public static function registration_page($error='') {
        if (is_user_logged_in()) { wp_safe_redirect(self::url()); exit; }
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE',true); }
        nocache_headers(); show_admin_bar(false); self::assets();
        include CCS_DIR.'templates/register.php';
    }
}
