<?php
require getenv('CCS_WP_PATH').'/wp-load.php';
$checks=0;
function mail_check($value,$message) { global $checks;if (!$value) { throw new Exception('FAIL '.$message); }$checks++;echo "PASS $message\n"; }
$mail_args=['to'=>'test@example.invalid','subject'=>'Test','message'=>'Test'];
if (getenv('CCS_TEST_NATIVE_MAIL')==='1') {
    $GLOBALS['phpmailer']=null;
    mail_check(is_callable('mail'),'native mail function available in control process');
    mail_check(CCS_Mail::prepare(null,$mail_args)===null && $GLOBALS['phpmailer']===null,'working native mail configuration is untouched');
    echo "TOTAL $checks mail checks\n";exit;
}
mail_check(!is_callable('mail'),'mail function disabled exactly as on affected hosting');
wp_set_current_user(get_user_by('login','admin')->ID);
// The disposable WordPress URL is localhost; use a valid sender to reach the native transport.
add_filter('wp_mail_from',fn()=>'wordpress@example.invalid');
$failures=[];$successes=0;$argument_runs=0;
add_action('wp_mail_failed',function($error) use (&$failures) { $failures[]=$error; });
add_action('wp_mail_succeeded',function() use (&$successes) { $successes++; });
add_filter('wp_mail',function($atts) use (&$argument_runs) { $argument_runs++;return $atts; });
$pre_success=fn()=>true;add_filter('pre_wp_mail',$pre_success,10);
mail_check(wp_mail('test@example.invalid','Handled externally','Body')===true,'external email service can preempt wp_mail');
remove_filter('pre_wp_mail',$pre_success,10);
$pre_failure=fn()=>false;add_filter('pre_wp_mail',$pre_failure,10);
mail_check(wp_mail('test@example.invalid','External failure','Body')===false,'external email failure is preserved');
remove_filter('pre_wp_mail',$pre_failure,10);
$before=$argument_runs;
mail_check(wp_mail('test@example.invalid','Native mail unavailable','Body')===false,'unavailable native mail returns false without PHP fatal');
mail_check($argument_runs===$before+1,'mail argument filters run once per call');
mail_check(count($failures)===1 && $failures[0]->get_error_code()==='wp_mail_failed' && $failures[0]->get_error_message()===CCS_Mail::UNAVAILABLE_MESSAGE && $successes===0,'standard WordPress mail failure emitted; no false native success');
mail_check($GLOBALS['phpmailer'] instanceof CCS_Safe_Mailer,'WordPress default mailer uses native transport guard');
$user_id=wp_insert_user(['user_login'=>'ccs_mail_test','user_email'=>'ccs-mail-test@example.invalid','user_pass'=>'Original-password-test!','role'=>'ccs_volunteer']);
mail_check(!is_wp_error($user_id),'mail regression user created');
$updated=wp_update_user(['ID'=>$user_id,'user_pass'=>'Updated-password-test!']);
mail_check($updated===$user_id && wp_check_password('Updated-password-test!',get_userdata($user_id)->user_pass,$user_id),'wp_update_user saves changed password with mail disabled');
$login=wp_authenticate('ccs-mail-test@example.invalid','Updated-password-test!');
mail_check($login instanceof WP_User && $login->ID===$user_id,'user logs in with password changed while notification fails');
$updated=wp_update_user(['ID'=>$user_id,'user_email'=>'ccs-mail-updated@example.invalid']);
mail_check($updated===$user_id && get_userdata($user_id)->user_email==='ccs-mail-updated@example.invalid','email change notification also fails gracefully');
$retrieved=retrieve_password('ccs-mail-updated@example.invalid');
mail_check(is_wp_error($retrieved) && in_array('retrieve_password_email_failure',$retrieved->get_error_codes(),true),'recovery email reports genuine delivery failure instead of fatal or fake success');
reset_password(get_userdata($user_id),'Reset-password-test!');
mail_check(wp_check_password('Reset-password-test!',get_userdata($user_id)->user_pass,$user_id),'password reset completes when admin notification cannot be mailed');
$notice_key='ccs_mail_notice_'.get_current_user_id();
mail_check(get_transient($notice_key)==='1','admin notice stores only flag without message, recipient or password');
ob_start();CCS_Mail::notice();$notice=ob_get_clean();
mail_check(str_contains($notice,'SMTP') && !str_contains($notice,'ccs-mail-updated@example.invalid'),'admin sees mail setup notice without personal data');
ob_start();CCS_Mail::notice();$second_notice=ob_get_clean();
mail_check($second_notice==='' && !get_transient($notice_key),'mail setup notice is consumed once');
$configured=function($mailer) {
    $mailer->isSMTP();$mailer->Host='127.0.0.1';$mailer->Port=25252;
    $mailer->SMTPAuth=false;$mailer->SMTPAutoTLS=false;$mailer->SMTPSecure='';$mailer->Timeout=3;
};
add_action('phpmailer_init',$configured);
$before_failed=count($failures);$before_success=$successes;
mail_check(wp_mail('smtp-test@example.invalid','SMTP guard compatibility','SMTP loopback test body',["X-CCS-Test: guard"])===true,'SMTP sends through loopback server with mail function disabled');
mail_check(count($failures)===$before_failed && $successes===$before_success+1,'SMTP emits success without guard failure');
$capture=file_get_contents('/tmp/ccs-smtp-capture.txt');
mail_check(str_contains($capture,'SMTP guard compatibility') && str_contains($capture,'X-CCS-Test: guard') && str_contains($capture,'SMTP loopback test body'),'SMTP sink received intended headers and body');
$before_success=$successes;
$updated=wp_update_user(['ID'=>$user_id,'user_pass'=>'SMTP-password-test!']);
mail_check($updated===$user_id && wp_check_password('SMTP-password-test!',get_userdata($user_id)->user_pass,$user_id) && $successes===$before_success+1,'password change still sends notification through configured SMTP');
remove_action('phpmailer_init',$configured);
mail_check(wp_mail('test@example.invalid','Native again','Body')===false,'later native attempt still fails gracefully after SMTP use');
$default=new \PHPMailer\PHPMailer\PHPMailer(true);$default->Host='preserved.example.invalid';$default->SMTPOptions=['ssl'=>['verify_peer'=>true]];
$GLOBALS['phpmailer']=$default;CCS_Mail::prepare(null,$mail_args);
mail_check($GLOBALS['phpmailer'] instanceof CCS_Safe_Mailer && $GLOBALS['phpmailer']->Host===$default->Host && $GLOBALS['phpmailer']->SMTPOptions===$default->SMTPOptions,'public settings from existing default mailer preserved');
class CCS_Test_Custom_Mailer extends \PHPMailer\PHPMailer\PHPMailer { public function send() { return true; } }
$custom=new CCS_Test_Custom_Mailer(true);$GLOBALS['phpmailer']=$custom;
mail_check(wp_mail('test@example.invalid','Custom transport','Body')===true && $GLOBALS['phpmailer']===$custom,'custom mailer supplied by another plugin is preserved');
echo "TOTAL $checks mail checks\n";
