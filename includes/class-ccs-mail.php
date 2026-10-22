<?php
if (!defined('ABSPATH')) { exit; }
final class CCS_Mail {
    public const UNAVAILABLE_MESSAGE='A função mail() está indisponível nesta hospedagem. Configure o envio por SMTP no WordPress para enviar avisos e links de recuperação de senha.';
    public static function boot() {
        add_filter('pre_wp_mail',[__CLASS__,'prepare'],PHP_INT_MAX,2);
        add_action('wp_mail_failed',[__CLASS__,'failed']);
        add_action('admin_notices',[__CLASS__,'notice']);
    }
    public static function prepare($pre,$atts) {
        // Respect services that already handled wp_mail(), and leave working native mail alone.
        if ($pre!==null || is_callable('mail')) { return $pre; }
        require_once ABSPATH.WPINC.'/PHPMailer/Exception.php';
        require_once ABSPATH.WPINC.'/PHPMailer/PHPMailer.php';
        require_once ABSPATH.WPINC.'/PHPMailer/SMTP.php';
        global $phpmailer;
        $existing=$phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer;
        // Custom transports supplied by other plugins keep their own mailer instance.
        if ($existing && !in_array(get_class($phpmailer),['PHPMailer\\PHPMailer\\PHPMailer','WP_PHPMailer'],true)) { return $pre; }
        require_once __DIR__.'/class-ccs-mailer.php';
        $safe=new CCS_Safe_Mailer(true);
        if ($existing) {
            // wp_mail() resets recipients/body itself; preserve public transport configuration.
            foreach (get_object_vars($phpmailer) as $property=>$value) { $safe->$property=$value; }
        } else {
            // Match the validator normally installed by WordPress when creating its mailer.
            $safe::$validator=static fn($email)=>(bool)is_email($email);
        }
        $phpmailer=$safe;
        return $pre;
    }
    public static function failed($error) {
        if ($error instanceof WP_Error && $error->get_error_message()===self::UNAVAILABLE_MESSAGE && current_user_can('manage_options')) {
            // Only a flag is retained: no recipients, reset links, messages or passwords.
            set_transient('ccs_mail_notice_'.get_current_user_id(),'1',10*MINUTE_IN_SECONDS);
        }
    }
    public static function notice() {
        if (!current_user_can('manage_options')) { return; }
        $key='ccs_mail_notice_'.get_current_user_id();
        if (!get_transient($key)) { return; }
        delete_transient($key);
        echo '<div class="notice notice-warning"><p>'.esc_html(self::UNAVAILABLE_MESSAGE).'</p></div>';
    }
}
