<?php
if (!defined('ABSPATH')) { exit; }
trait CCS_Native_Mail_Guard {
    protected function mailSend($header,$body) {
        if (!is_callable('mail')) {
            // WordPress catches PHPMailer exceptions; PHP's undefined-function Error is fatal.
            throw new \PHPMailer\PHPMailer\Exception(CCS_Mail::UNAVAILABLE_MESSAGE,1);
        }
        return parent::mailSend($header,$body);
    }
}
// WordPress introduced WP_PHPMailer in 6.8; retain its translations where available.
if (file_exists(ABSPATH.WPINC.'/class-wp-phpmailer.php')) {
    require_once ABSPATH.WPINC.'/class-wp-phpmailer.php';
    class CCS_Safe_Mailer extends WP_PHPMailer { use CCS_Native_Mail_Guard; }
} else {
    class CCS_Safe_Mailer extends \PHPMailer\PHPMailer\PHPMailer { use CCS_Native_Mail_Guard; }
}
