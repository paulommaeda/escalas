<?php
if (!defined('ABSPATH')) { exit; }

function ccs_github_repository() {
    $repo=trim((string)get_option('ccs_repo',''));
    return $repo!==''?$repo:'paulommaeda/escalas';
}
function ccs_github_enabled() { return get_option('ccs_github_enabled','1')==='1'; }
function ccs_github_cache_key() { return 'ccs_release_'.md5(ccs_github_repository()); }
function ccs_github_allowed() { return current_user_can('manage_options') && current_user_can('update_plugins'); }

/** Only a stable release containing the official installable asset is eligible. */
function ccs_github_check($force=false) {
    $repo=ccs_github_repository();
    $key=ccs_github_cache_key();
    if (!$force && ($cached=get_transient($key))!==false) { return $cached; }
    $result=['ok'=>false,'repo'=>$repo,'checked_at'=>time(),'message'=>'','version'=>'','package'=>''];
    if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D',$repo)) {
        $result['message']='Informe o repositório no formato proprietário/repositorio.';
    } else {
        $response=wp_safe_remote_get('https://api.github.com/repos/'.$repo.'/releases/latest',[
            'timeout'=>12,'redirection'=>2,'limit_response_size'=>524288,
            'headers'=>['Accept'=>'application/vnd.github+json','User-Agent'=>'CCShalom-Escalas/'.CCS_VERSION],
        ]);
        if (is_wp_error($response)) { $result['message']='Não foi possível consultar o GitHub. Verifique a conexão da hospedagem e tente novamente.'; }
        else {
            $code=(int)wp_remote_retrieve_response_code($response);
            $data=json_decode(wp_remote_retrieve_body($response),true);
            if ($code===404) { $result['message']='Nenhuma versão estável pública encontrada. Confira o repositório e a publicação da release.'; }
            elseif ($code===403 || $code===429) { $result['message']='O GitHub limitou temporariamente as consultas. Aguarde e tente novamente.'; }
            elseif ($code!==200 || !is_array($data)) { $result['message']='O GitHub retornou uma resposta inválida. Tente novamente mais tarde.'; }
            else {
                $version=preg_replace('/^v/','',(string)($data['tag_name']??''));
                if (!empty($data['draft']) || !empty($data['prerelease']) || !preg_match('/^\d+\.\d+\.\d+$/D',$version)) {
                    $result['message']='A publicação não é uma versão estável válida (exemplo: v0.9.0).';
                } else {
                    $package='';
                    foreach (($data['assets']??[]) as $asset) {
                        $url=(string)($asset['browser_download_url']??'');
                        $prefix='https://github.com/'.$repo.'/releases/download/';
                        if (($asset['name']??'')==='ccshalom-escalas.zip' && str_starts_with($url,$prefix) && wp_parse_url($url,PHP_URL_HOST)==='github.com' && !str_contains($url,'?') && str_ends_with($url,'/ccshalom-escalas.zip')) { $package=$url;break; }
                    }
                    if ($package==='') { $result['message']='A release precisa incluir o arquivo instalável ccshalom-escalas.zip.'; }
                    else {
                        $result=array_merge($result,['ok'=>true,'version'=>$version,'package'=>$package,'url'=>'https://github.com/'.$repo.'/releases','notes'=>sanitize_textarea_field($data['body']??''),'message'=>version_compare($version,CCS_VERSION,'>')?'Nova versão disponível para atualizar.':'A versão instalada está atualizada.']);
                    }
                }
            }
        }
    }
    set_transient($key,$result,$result['ok']?30*MINUTE_IN_SECONDS:5*MINUTE_IN_SECONDS);
    return $result;
}
function ccs_release() {
    if (!ccs_github_enabled()) { return false; }
    $release=ccs_github_check();
    return !empty($release['ok'])?$release:false;
}
function ccs_github_update_info($update,$plugin_data,$plugin_file,$locales) {
    if ($plugin_file!==plugin_basename(CCS_FILE)) { return $update; }
    $r=ccs_release();if (!$r) { return false; }
    return ['id'=>'https://github.com/paulommaeda/escalas','slug'=>'ccshalom-escalas','version'=>$r['version'],'url'=>$r['url'],'package'=>$r['package'],'requires'=>'6.4','requires_php'=>'8.0'];
}
add_filter('update_plugins_github.com','ccs_github_update_info',10,4);
add_filter('plugins_api',function($result,$action,$args) {
    if ($action!=='plugin_information' || ($args->slug??'')!=='ccshalom-escalas') { return $result; }
    $r=ccs_release();if (!$r) { return $result; }
    return (object)['name'=>'CCShalom — Escalas','slug'=>'ccshalom-escalas','version'=>$r['version'],'author'=>'CCShalom','homepage'=>$r['url'],'download_link'=>$r['package'],'requires'=>'6.4','requires_php'=>'8.0','sections'=>['description'=>'Escalas de voluntários da CCShalom.','changelog'=>nl2br(esc_html($r['notes']))]];
},10,3);
add_action('admin_menu',function() {
    add_submenu_page('ccs-escalas','Atualizações via GitHub','Atualizações','manage_options','ccs-updates','ccs_github_page');
},20);
add_action('admin_post_ccs_github_settings',function() {
    if (!ccs_github_allowed()) { wp_die('Sem permissão.', '', ['response'=>403]); }
    check_admin_referer('ccs_github_settings');
    if (($_POST['intent']??'')==='save') {
        $repo=trim((string)wp_unslash($_POST['repo']??''));
        $repo=preg_replace('~^https://github\.com/~i','',$repo);
        $repo=preg_replace('~\.git$~','',rtrim($repo,'/'));
        if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D',$repo)) { wp_die('Informe o repositório no formato proprietário/repositorio.'); }
        delete_transient(ccs_github_cache_key());
        update_option('ccs_repo',$repo);
        update_option('ccs_github_enabled',isset($_POST['enabled'])?'1':'0');
        $plugins=(array)get_site_option('auto_update_plugins',[]);
        $plugins=array_values(array_diff($plugins,[plugin_basename(CCS_FILE)]));
        if (isset($_POST['automatic']) && isset($_POST['enabled'])) { $plugins[]=plugin_basename(CCS_FILE); }
        update_site_option('auto_update_plugins',$plugins);
    }
    delete_transient('ccs_release');delete_transient(ccs_github_cache_key());
    if (ccs_github_enabled()) { ccs_github_check(true); }
    wp_clean_plugins_cache(true);
    wp_update_plugins();
    wp_safe_redirect(admin_url('admin.php?page=ccs-updates&ccs_saved=1'));exit;
});
function ccs_github_page() {
    if (!ccs_github_allowed()) { wp_die('Sem permissão.', '', ['response'=>403]); }
    $repo=ccs_github_repository();$result=get_transient(ccs_github_cache_key());
    $automatic=in_array(plugin_basename(CCS_FILE),(array)get_site_option('auto_update_plugins',[]),true);
    echo '<div class="wrap"><h1>Atualizações via GitHub</h1><p>Receba as versões publicadas do CCShalom Escalas pelo atualizador do WordPress.</p>';
    if (isset($_GET['ccs_saved'])) { echo '<div class="notice notice-success"><p>Configuração e consulta atualizadas.</p></div>'; }
    echo '<p><strong>Versão instalada:</strong> '.esc_html(CCS_VERSION).'</p>';
    if (!ccs_github_enabled()) { echo '<p>Consulta ao GitHub desativada.</p>'; }
    elseif (is_array($result)) {
        echo '<p><strong>Status:</strong> '.esc_html($result['message']).'</p><p><strong>Última consulta:</strong> '.esc_html(wp_date('d/m/Y H:i',$result['checked_at'])).'</p>';
        if (!empty($result['ok'])) { echo '<p><strong>Última versão publicada:</strong> '.esc_html($result['version']).'</p>'; }
    } else { echo '<p>Clique em Verificar agora para consultar a última versão publicada.</p>'; }
    echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="ccs_github_settings">';
    wp_nonce_field('ccs_github_settings');
    echo '<table class="form-table"><tr><th><label for="ccs-update-repo">Repositório público</label></th><td><input id="ccs-update-repo" class="regular-text" name="repo" value="'.esc_attr($repo).'" required><p class="description">Exemplo: paulommaeda/escalas. Aceita também a URL do repositório.</p></td></tr><tr><th>Consultar atualizações</th><td><label><input type="checkbox" name="enabled" '.checked(ccs_github_enabled(),true,false).'> Habilitar atualizações via GitHub</label></td></tr><tr><th>Instalação automática</th><td><label><input type="checkbox" name="automatic" '.checked($automatic,true,false).'> Permitir que o WordPress instale novas versões automaticamente</label><p class="description">Sem esta opção, você escolhe quando atualizar na página Plugins. A instalação automática depende do agendador do WordPress e das regras da hospedagem.</p></td></tr></table>';
    echo '<p class="submit"><button class="button button-primary" type="submit" name="intent" value="save">Salvar configurações</button> <button class="button" type="submit" name="intent" value="check" formnovalidate>Verificar agora</button></p><p>Verificar agora utiliza as configurações já salvas.</p></form>';
    echo '<p><a class="button" href="'.esc_url(admin_url('plugins.php')).'">Abrir atualizador de plugins</a> <a href="'.esc_url('https://github.com/'.$repo.'/releases').'" target="_blank" rel="noopener noreferrer">Ver versões no GitHub</a></p><p>Somente releases estáveis com o arquivo <code>ccshalom-escalas.zip</code> são usadas. Código enviado sem uma nova versão publicada não gera atualização.</p></div>';
}
