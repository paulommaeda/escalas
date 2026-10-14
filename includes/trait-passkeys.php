<?php
if (!defined('ABSPATH')) { exit; }
// Isolated vendor namespace prevents collisions with other WordPress plugins.
spl_autoload_register(function($class) {
    $prefix='CCShalom\\Vendor\\WebAuthn\\';
    if (str_starts_with($class,$prefix)) {
        $relative=substr($class,strlen($prefix));
        if (preg_match('/^[A-Za-z0-9_\\\\]+$/D',$relative)) { $file=__DIR__.'/../vendor/webauthn/src/'.str_replace('\\','/',$relative).'.php'; if (is_file($file)) { require_once $file; } }
    }
});
trait CCS_Passkeys {
    public static function passkey_origin() {
        $p=wp_parse_url(home_url('/'));
        return strtolower($p['scheme'].'://'.$p['host']).(isset($p['port'])?':'.$p['port']:'');
    }
    public static function passkey_available() {
        return function_exists('openssl_verify') && (wp_parse_url(home_url('/'),PHP_URL_SCHEME)==='https' || wp_parse_url(home_url('/'),PHP_URL_HOST)==='localhost');
    }
    private static function webauthn() { return new \CCShalom\Vendor\WebAuthn\WebAuthn('CCShalom Escalas',wp_parse_url(home_url('/'),PHP_URL_HOST),['none'],true); }
    public static function passkey_list() {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT id,label,created_at,last_used FROM '.self::table('passkeys').' WHERE user_id=%d ORDER BY id DESC',get_current_user_id()),ARRAY_A);
    }
    public static function revoke_all_keys($uid) { global $wpdb;$wpdb->delete(self::table('passkeys'),['user_id'=>$uid]); }
    private static function user_handle($uid) {
        $handle=get_user_meta($uid,'ccs_passkey_handle',true);
        if (!$handle) { $handle=bin2hex(random_bytes(32));update_user_meta($uid,'ccs_passkey_handle',$handle); }
        return hex2bin($handle);
    }
    private static function b64($bin) { return rtrim(strtr(base64_encode($bin),'+/','-_'),'='); }
    private static function unb64($value,$max=65536) {
        self::require_that(is_string($value) && $value!=='' && strlen($value)<=$max && preg_match('/^[A-Za-z0-9_-]+$/D',$value),'Resposta do dispositivo inválida.',400);
        $bin=base64_decode(strtr($value,'-_','+/'),true);
        self::require_that($bin!==false,'Resposta do dispositivo inválida.',400);return $bin;
    }
    public static function passkey_routes() {
        foreach (['register-options','register','login-options','login','revoke'] as $action) {
            register_rest_route('ccshalom/v1','/passkeys/'.$action,['methods'=>'POST','permission_callback'=>str_starts_with($action,'login')?'__return_true':[__CLASS__,'access'],'callback'=>function($r) use($action) {return self::passkey_request($action,$r);}]);
        }
    }
    private static function challenge_save($kind,$challenge,$label='') {
        $token=bin2hex(random_bytes(32));$uid=$kind==='register'?get_current_user_id():0;
        set_transient('ccs_pk_'.$token,['kind'=>$kind,'challenge'=>self::b64($challenge),'binding'=>self::auth_binding(),'uid'=>$uid,'session'=>$uid?hash('sha256',wp_get_session_token()):'','label'=>$label,'expires'=>time()+180],180);
        return $token;
    }
    private static function challenge_take($token,$kind) {
        self::require_that(is_string($token) && preg_match('/^[a-f0-9]{64}$/D',$token),'Tentativa inválida. Tente novamente.',400);
        $c=get_transient('ccs_pk_'.$token);delete_transient('ccs_pk_'.$token);
        self::require_that(is_array($c) && $c['kind']===$kind && $c['expires']>=time() && self::auth_binding() && hash_equals($c['binding'],self::auth_binding()),'O acesso expirou ou pertence a outro navegador. Tente novamente.',403);
        if ($kind==='register') { self::require_that($c['uid']===get_current_user_id() && hash_equals($c['session'],hash('sha256',wp_get_session_token())),'Entre novamente antes de ativar a biometria.',403); }
        return $c;
    }
    private static function client_data($encoded) {
        $raw=self::unb64($encoded,16384);$c=json_decode($raw,true);
        self::require_that(is_array($c) && ($c['origin']??'')===self::passkey_origin() && empty($c['crossOrigin']) && !isset($c['topOrigin']),'Origem do acesso inválida.',403);
        return $raw;
    }
    public static function passkey_request($action,$request) {
        global $wpdb;$locked=false;$lock='ccs_pk_'.md5(DB_NAME.$wpdb->prefix);
        try {
            self::require_that(self::passkey_available(),'O acesso por biometria precisa de HTTPS e OpenSSL na hospedagem.',400);
            self::require_that($request->get_header('origin')===self::passkey_origin(),'Abra o aplicativo no endereço oficial para continuar.',403);
            $a=$request->get_json_params();self::require_that(is_array($a) && strlen($request->get_body())<=100000,'Dados inválidos.',400);
            if (str_starts_with($action,'login')) { self::auth_limit('passkey',60); }
            $locked=(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock))===1;
            self::require_that($locked,'Outro acesso está sendo processado. Tente novamente.',409);
            self::require_that(self::auth_binding(true)!=='','Permita os cookies do aplicativo para continuar.',403);
            if ($action==='revoke') {
                $id=absint($a['id']??0);$n=$wpdb->delete(self::table('passkeys'),['id'=>$id,'user_id'=>get_current_user_id()]);
                self::require_that($n===1,'Acesso não encontrado no seu perfil.',404);
                return ['ok'=>true,'message'=>'Acesso por biometria removido.'];
            }
            $wa=self::webauthn();
            if ($action==='register-options') {
                $uid=get_current_user_id();$u=wp_get_current_user();
                self::auth_limit('enroll',15);
                self::require_that(is_string($a['password']??null) && wp_check_password($a['password'],$u->user_pass,$uid),'Confirme sua senha atual para ativar este acesso.',403);
                self::require_that(count(self::passkey_list())<10,'Limite de 10 acessos. Remova um antigo antes de cadastrar outro.',400);
                $label=self::text($a,'label',100);
                $ids=$wpdb->get_col($wpdb->prepare('SELECT credential_id FROM '.self::table('passkeys').' WHERE user_id=%d',$uid));
                $options=$wa->getCreateArgs(self::user_handle($uid),$u->user_email,$u->display_name,60,true,true,false,array_map(fn($id)=>self::unb64($id),$ids));
                $options->publicKey->authenticatorSelection->residentKey='required';
                return ['options'=>$options->publicKey,'request_id'=>self::challenge_save('register',$wa->getChallenge()->getBinaryString(),$label)];
            }
            if ($action==='login-options') {
                $options=$wa->getGetArgs([],60,true,true,true,true,true,true);
                return ['options'=>$options->publicKey,'request_id'=>self::challenge_save('login',$wa->getChallenge()->getBinaryString())];
            }
            $kind=$action==='register'?'register':'login';$c=self::challenge_take($a['request_id']??'',$kind);
            $client=self::client_data($a['clientDataJSON']??'');$credential=self::unb64($a['id']??'',2048);
            if ($action==='register') {
                $result=$wa->processCreate($client,self::unb64($a['attestationObject']??''),self::unb64($c['challenge']),true,true);
                self::require_that(hash_equals($result->credentialId,$credential),'Identificador de acesso inválido.',400);
                self::require_that(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('passkeys').' WHERE credential_hash=%s',hash('sha256',$credential))),'Este acesso já está cadastrado.',409);
                self::insert('passkeys',['user_id'=>$c['uid'],'credential_hash'=>hash('sha256',$credential),'credential_id'=>self::b64($credential),'public_key'=>$result->credentialPublicKey,'sign_count'=>(int)$result->signatureCounter,'label'=>$c['label'],'created_at'=>current_time('mysql')]);
                return ['ok'=>true,'message'=>'Biometria ativada! Você já pode usá-la no próximo acesso.'];
            }
            $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('passkeys').' WHERE credential_hash=%s',hash('sha256',$credential)),ARRAY_A);
            self::require_that($row && self::participant($row['user_id']),'Este acesso não está ativo. Entre com sua senha.',403);
            self::require_that(hash_equals(self::user_handle($row['user_id']),self::unb64($a['userHandle']??'',512)),'O acesso não pertence a esta conta.',403);
            $wa->processGet($client,self::unb64($a['authenticatorData']??'',4096),self::unb64($a['signature']??'',4096),$row['public_key'],self::unb64($c['challenge']),(int)$row['sign_count'],true,true);
            self::update('passkeys',['sign_count'=>(int)$wa->getSignatureCounter(),'last_used'=>current_time('mysql')],['id'=>$row['id']]);
            $u=get_userdata($row['user_id']);wp_set_current_user($u->ID);wp_set_auth_cookie($u->ID,!empty($a['remember']),is_ssl());do_action('wp_login',$u->user_login,$u);
            return ['ok'=>true,'redirect'=>self::url()];
        } catch (CCS_Exception $e) { return new WP_Error('ccs_passkey',$e->getMessage(),['status'=>$e->getCode()?:400]); }
        catch (Throwable $e) { return new WP_Error('ccs_passkey','Não foi possível validar o acesso do dispositivo. Tente novamente ou entre com sua senha.',['status'=>400]); }
        finally { if ($locked) { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); } }
    }
}
