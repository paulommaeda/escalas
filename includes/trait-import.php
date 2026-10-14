<?php
if (!defined('ABSPATH')) { exit; }
trait CCS_Import {
    public static function import_allowed() { return current_user_can('manage_options') && !self::secretary(); }
    public static function import_url() { return admin_url('admin.php?page=ccs-import'); }
    public static function import_roles() {
        return ['voluntario'=>'ccs_volunteer','lider_de_equipe'=>'ccs_team','lider_de_departamento'=>'ccs_department','pastor'=>'ccs_pastor','secretaria'=>'ccs_secretary'];
    }
    public static function import_csv($raw) {
        self::require_that(strlen($raw)<=2*1024*1024,'O arquivo deve ter até 2 MB.',400);
        $raw=preg_replace('/^\xEF\xBB\xBF/','',$raw);
        self::require_that($raw!=='' && !str_contains($raw,"\0") && preg_match('//u',$raw),'Salve o arquivo como CSV UTF-8.',400);
        $stream=fopen('php://temp','r+');fwrite($stream,$raw);rewind($stream);
        $first=fgets($stream);rewind($stream);
        $delimiter=count(str_getcsv($first,';','"',''))>count(str_getcsv($first,',','"',''))?';':',';
        $header=fgetcsv($stream,0,$delimiter,'"','');
        $header=array_map(fn($v)=>strtolower(trim(remove_accents((string)$v))),$header?:[]);
        self::require_that(count($header)===count(array_unique($header)) && !array_diff(['nome','email'],$header) && !array_diff($header,['nome','email','telefone','perfil']),'Cabeçalho esperado: nome;email;telefone;perfil. Nome e email são obrigatórios.',400);
        $rows=[];$seen=[];$line=1;
        while (($values=fgetcsv($stream,0,$delimiter,'"',''))!==false) {
            $line++;
            if (count($values)===1 && trim((string)$values[0])==='') { continue; }
            self::require_that(count($rows)<200,'Importe no máximo 200 pessoas por arquivo.',400);
            $row=['line'=>$line,'name'=>'','email'=>'','phone'=>'','role'=>'','status'=>'invalid','message'=>''];
            if (count($values)!==count($header)) { $row['message']='Quantidade de colunas diferente do cabeçalho.';$rows[]=$row;continue; }
            $data=array_combine($header,array_map(fn($v)=>trim((string)$v),$values));
            $row['name']=sanitize_text_field($data['nome']);
            $row['email']=strtolower($data['email']);
            $row['phone']=preg_replace('/[^0-9]/','',$data['telefone']??'');
            $profile=str_replace([' ','-'],['_','_'],strtolower(remove_accents($data['perfil']??'')));
            $profile=$profile?:'voluntario';$row['role']=self::import_roles()[$profile]??'';
            if ($row['name']==='' || mb_strlen($row['name'])>150) { $row['message']='Nome obrigatório, com até 150 caracteres.'; }
            elseif (!is_email($row['email']) || strlen($row['email'])>100) { $row['message']='E-mail inválido ou muito longo.'; }
            elseif (($data['telefone']??'')!=='' && (strlen($row['phone'])<10 || strlen($row['phone'])>15)) { $row['message']='Telefone deve ter DDD e entre 10 e 15 dígitos.'; }
            elseif (!$row['role']) { $row['message']='Perfil inválido. Use os valores do modelo.'; }
            elseif (isset($seen[$row['email']])) { $row['status']='skipped';$row['message']='E-mail repetido neste arquivo.'; }
            elseif (email_exists($row['email'])) { $row['status']='skipped';$row['message']='E-mail já cadastrado; a conta será preservada.'; }
            else { $row['status']='ready';$row['message']='Pronto para cadastrar.'; }
            if (in_array($row['status'],['ready','skipped'],true)) { $seen[$row['email']]=true; }
            $rows[]=$row;
        }
        fclose($stream);self::require_that(count($rows)>0,'O arquivo não contém pessoas.',400);
        return $rows;
    }
    public static function import_template() {
        if (!self::import_allowed()) { wp_die('Sem permissão.', '', ['response'=>403]); }
        check_admin_referer('ccs_import_template');nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="modelo-voluntarios.csv"');
        echo "\xEF\xBB\xBF".'nome;email;telefone;perfil'."\r\n".'Pessoa Exemplo;pessoa@example.com;41999999999;voluntario'."\r\n";exit;
    }
    public static function import_page() {
        if (!self::import_allowed()) { wp_die('Sem permissão.', '', ['response'=>403]); }
        nocache_headers();$key='ccs_import_'.get_current_user_id();$error='';$notice='';
        if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
            check_admin_referer('ccs_import_users');
            try {
                $action=sanitize_key($_POST['import_action']??'');
                if ($action==='preview') {
                    delete_transient($key);
                    $file=$_FILES['csv']??null;
                    self::require_that($file && (int)$file['error']===UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name']),'Selecione um arquivo CSV válido.',400);
                    self::require_that(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))==='csv','Envie um arquivo .csv, exportado em UTF-8.',400);
                    self::require_that((int)$file['size']<=2*1024*1024,'Limite de 2 MB por arquivo.',400);
                    $raw=file_get_contents($file['tmp_name']);self::require_that(is_string($raw),'Não foi possível ler o arquivo.',400);
                    $batch=['token'=>wp_generate_password(32,false,false),'rows'=>self::import_csv($raw),'done'=>false];
                    self::require_that(set_transient($key,$batch,HOUR_IN_SECONDS),'Não foi possível guardar a prévia. Tente novamente.',500);
                } elseif ($action==='commit') {
                    global $wpdb;$lock='ccs_'.md5(DB_NAME.$wpdb->prefix);
                    self::require_that((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock))===1,'Outra alteração está em andamento. Tente novamente.',409);
                    try {
                        $batch=get_transient($key);$token=wp_unslash($_POST['token']??'');
                        self::require_that(is_array($batch) && is_string($token) && hash_equals($batch['token'],$token),'A prévia expirou ou foi substituída. Envie o CSV novamente.',409);
                        self::require_that(isset($_POST['confirm']),'Confirme os cadastros antes de continuar.',400);
                        if (!$batch['done']) {
                            // No emails are sent. Existing accounts, roles and passwords remain untouched.
                            foreach ($batch['rows'] as &$row) {
                                if ($row['status']!=='ready') { continue; }
                                if (email_exists($row['email'])) { $row['status']='skipped';$row['message']='E-mail já cadastrado; nenhuma alteração.';continue; }
                                $id=wp_insert_user(['user_login'=>'ccs_'.strtolower(wp_generate_password(24,false,false)),'user_email'=>$row['email'],'display_name'=>$row['name'],'user_pass'=>wp_generate_password(40,true,true),'role'=>$row['role']]);
                                if (is_wp_error($id)) { $row['status']='failed';$row['message']='Não foi possível cadastrar: '.$id->get_error_message();continue; }
                                update_user_meta($id,'ccs_phone',$row['phone']);
                                $row['status']='created';$row['message']='Pessoa cadastrada.';$row['user_id']=$id;
                                // Audit records the target ID; passwords and CSV contents never enter the log.
                                self::audit('user_imported',0,(string)$id);
                            } unset($row);
                            $batch['done']=true;set_transient($key,$batch,HOUR_IN_SECONDS);
                        }
                        $notice='Importação concluída. Confira os resultados por linha.';
                    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
                } elseif ($action==='clear') { delete_transient($key); }
            } catch (CCS_Exception $e) { $error=$e->getMessage(); }
            catch (Throwable $e) { $error='Não foi possível concluir a importação. Consulte os resultados e envie novamente o CSV para retomar; e-mails existentes serão ignorados.'; }
        }
        $batch=get_transient($key);
        echo '<div class="wrap"><h1>Importar pessoas</h1><p>Envie um CSV UTF-8, exportado pelo Excel ou Google Planilhas. Limites: 200 pessoas e 2 MB. Aceita vírgula ou ponto e vírgula.</p>';
        echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=ccs_import_template'),'ccs_import_template')).'">Baixar modelo CSV</a></p>';
        echo '<p>Colunas: <code>nome;email;telefone;perfil</code>. Nome e e-mail são obrigatórios. Telefone é opcional. Perfis: <code>voluntario</code> (padrão), <code>lider_de_equipe</code>, <code>lider_de_departamento</code>, <code>pastor</code> ou <code>secretaria</code>. Não é possível criar administradores por importação.</p>';
        echo '<p>Contas existentes não são alteradas. As pessoas novas ficam ativas, sem equipe ou função. Para o primeiro acesso, cada pessoa usa <strong>Perdeu a senha?</strong> com o e-mail importado. Não há envio automático de e-mails nesta importação.</p>';
        if ($error) { echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>'; }
        if ($notice) { echo '<div class="notice notice-success"><p>'.esc_html($notice).'</p></div>'; }
        echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(self::import_url()).'">';wp_nonce_field('ccs_import_users');
        echo '<input type="hidden" name="import_action" value="preview"><label for="ccs-import-csv">Arquivo CSV</label> <input id="ccs-import-csv" type="file" name="csv" accept=".csv,text/csv" required>';
        submit_button('Validar e visualizar');echo '</form>';
        if (is_array($batch)) {
            $counts=array_count_values(array_column($batch['rows'],'status'));$ready=$counts['ready']??0;
            echo '<h2>'.($batch['done']?'Resultado':'Prévia').'</h2><p>'.esc_html(sprintf('Prontos: %d · Criados: %d · Ignorados: %d · Inválidos/falhas: %d',$ready,$counts['created']??0,$counts['skipped']??0,($counts['invalid']??0)+($counts['failed']??0))).'</p>';
            echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Registro</th><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Perfil</th><th>Resultado</th></tr></thead><tbody>';
            foreach ($batch['rows'] as $row) {
                echo '<tr>';foreach (['line','name','email','phone','role','message'] as $field) { echo '<td>'.esc_html($row[$field]).'</td>'; }echo '</tr>';
            }echo '</tbody></table></div>';
            if (!$batch['done'] && $ready) {
                echo '<form method="post" action="'.esc_url(self::import_url()).'">';wp_nonce_field('ccs_import_users');
                echo '<input type="hidden" name="import_action" value="commit"><input type="hidden" name="token" value="'.esc_attr($batch['token']).'"><p><label><input type="checkbox" name="confirm" required> Confirmo a criação somente das pessoas marcadas como prontas, com os perfis indicados. Linhas inválidas e duplicadas serão ignoradas.</label></p>';
                submit_button('Importar '.$ready.' pessoas');echo '</form>';
            }
            echo '<form method="post" action="'.esc_url(self::import_url()).'">';wp_nonce_field('ccs_import_users');echo '<input type="hidden" name="import_action" value="clear">';submit_button('Limpar prévia/resultado','secondary');echo '</form><p>A prévia e o resultado ficam disponíveis por até 1 hora, somente para o administrador que enviou o arquivo.</p>';
        }
        echo '</div>';
    }
}
