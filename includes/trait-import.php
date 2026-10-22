<?php
if (!defined('ABSPATH')) { exit; }
trait CCS_Import {
    public static function import_allowed() { return current_user_can('manage_options') && !self::secretary(); }
    public static function import_url() { return admin_url('admin.php?page=ccs-import'); }
    public static function import_roles() {
        return ['voluntario'=>'ccs_volunteer','lider_de_equipe'=>'ccs_team','lider_de_departamento'=>'ccs_department','pastor'=>'ccs_pastor','secretaria'=>'ccs_secretary'];
    }
    public static function import_fields() {
        return ['name'=>['Nome',true], 'email'=>['E-mail',true], 'birth_date'=>['Data de nascimento',true], 'phone'=>['Telefone',false], 'role'=>['Perfil',false]];
    }
    public static function import_source($raw) {
        self::require_that(is_string($raw) && strlen($raw)<=2*1024*1024,'O arquivo deve ter até 2 MB.',400);
        $raw=preg_replace('/^\xEF\xBB\xBF/','',$raw);
        self::require_that($raw!=='' && !str_contains($raw,"\0") && preg_match('//u',$raw),'Salve o arquivo como CSV UTF-8.',400);
        $stream=fopen('php://temp','r+');fwrite($stream,$raw);rewind($stream);
        try {
            $first=fgets($stream);rewind($stream);
            $delimiter=count(str_getcsv($first,';','"',''))>count(str_getcsv($first,',','"',''))?';':',';
            $header=fgetcsv($stream,0,$delimiter,'"','');
            self::require_that(is_array($header) && count($header)>=3 && count($header)<=100,'Inclua uma linha de cabeçalho e entre 3 e 100 colunas.',400);
            $header=array_map(fn($v)=>mb_substr(sanitize_text_field(trim((string)$v)),0,150),$header);
            $records=[];$line=1;
            while (($values=fgetcsv($stream,0,$delimiter,'"',''))!==false) {
                $line++;
                $values=array_map(fn($v)=>trim((string)$v),$values);
                if (!array_filter($values,fn($v)=>$v!=='')) { continue; }
                self::require_that(count($records)<200,'Importe no máximo 200 pessoas por arquivo.',400);
                $records[]=['line'=>$line,'values'=>$values];
            }
            self::require_that(count($records)>0,'O arquivo não contém pessoas.',400);
            return ['header'=>$header,'records'=>$records];
        } finally { fclose($stream); }
    }
    public static function import_auto_mapping($header) {
        $aliases=[
            'name'=>['nome','nome_completo','name','full_name','pessoa'],
            'email'=>['email','e_mail','correio_eletronico'],
            'birth_date'=>['data_nascimento','data_de_nascimento','nascimento','dt_nascimento','dtnascimento','dtnasc','birth_date','birthday'],
            'phone'=>['telefone','celular','fone','phone','whatsapp'],
            'role'=>['perfil','perfil_de_acesso','role'],
        ];
        $mapping=array_fill_keys(array_keys(self::import_fields()),'');
        foreach ($header as $index=>$label) {
            $label=trim(preg_replace('/[^a-z0-9]+/','_',strtolower(remove_accents($label))),'_');
            foreach ($aliases as $field=>$names) {
                if ($mapping[$field]==='' && in_array($label,$names,true)) { $mapping[$field]=(string)$index;break; }
            }
        }
        return $mapping;
    }
    public static function import_validate_mapping($mapping,$columns) {
        self::require_that(is_array($mapping),'Selecione as colunas para importar.',400);
        $result=[];$used=[];
        foreach (self::import_fields() as $field=>[$label,$required]) {
            $value=$mapping[$field]??'';
            self::require_that(is_string($value) || is_int($value),'Mapeamento inválido: '.$label.'.',400);
            if ($value==='') {
                self::require_that(!$required,'Selecione uma coluna para '.$label.'.',400);
                $result[$field]='';continue;
            }
            self::require_that(preg_match('/^(0|[1-9][0-9]*)$/',(string)$value) && (int)$value<$columns,'Coluna inválida para '.$label.'.',400);
            $index=(int)$value;
            self::require_that(!isset($used[$index]),'Cada coluna pode ser usada em apenas um campo.',400);
            $used[$index]=true;$result[$field]=$index;
        }
        return $result;
    }
    public static function import_birth_date($value) {
        $value=trim((string)$value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$value,$m)) { [$year,$month,$day]=[(int)$m[1],(int)$m[2],(int)$m[3]]; }
        elseif (preg_match('/^(\d{1,2})([\/.-])(\d{1,2})\2(\d{4})$/',$value,$m)) { [$year,$month,$day]=[(int)$m[4],(int)$m[3],(int)$m[1]]; }
        elseif (preg_match('/^(\d{2})(\d{2})(\d{4})$/',$value,$m)) { [$year,$month,$day]=[(int)$m[3],(int)$m[2],(int)$m[1]]; }
        else { return ''; }
        if (!checkdate($month,$day,$year)) { return ''; }
        $date=sprintf('%04d-%02d-%02d',$year,$month,$day);
        return $date<=current_datetime()->format('Y-m-d')?$date:'';
    }
    public static function import_rows($source,$mapping) {
        $mapping=self::import_validate_mapping($mapping,count($source['header']));
        $rows=[];$seen=[];
        foreach ($source['records'] as $record) {
            $row=['line'=>$record['line'],'name'=>'','email'=>'','birth_date'=>'','phone'=>'','role'=>'','status'=>'invalid','message'=>''];
            if (count($record['values'])!==count($source['header'])) { $row['message']='Quantidade de colunas diferente do cabeçalho.';$rows[]=$row;continue; }
            $data=[];foreach ($mapping as $field=>$index) { $data[$field]=$index===''?'':$record['values'][$index]; }
            $row['name']=sanitize_text_field($data['name']);
            $row['email']=strtolower($data['email']);
            $row['birth_date']=self::import_birth_date($data['birth_date']);
            $row['phone']=preg_replace('/[^0-9]/','',$data['phone']);
            $profile=str_replace([' ','-'],['_','_'],strtolower(remove_accents($data['role'])));
            $row['role']=self::import_roles()[$profile?:'voluntario']??'';
            if ($row['name']==='' || mb_strlen($row['name'])>150) { $row['message']='Nome obrigatório, com até 150 caracteres.'; }
            elseif (!is_email($row['email']) || strlen($row['email'])>100) { $row['message']='E-mail inválido ou muito longo.'; }
            elseif (isset($seen[$row['email']])) { $row['status']='skipped';$row['message']='E-mail repetido neste arquivo.'; }
            elseif (email_exists($row['email'])) { $row['status']='skipped';$row['message']='E-mail já cadastrado; a conta será preservada.'; }
            elseif (!$row['birth_date']) { $row['message']='Data de nascimento obrigatória e válida. Use dd/mm/aaaa, aaaa-mm-dd ou ddmmaaaa, sem data futura.'; }
            elseif ($data['phone']!=='' && (strlen($row['phone'])<10 || strlen($row['phone'])>15)) { $row['message']='Telefone deve ter DDD e entre 10 e 15 dígitos.'; }
            elseif (!$row['role']) { $row['message']='Perfil inválido. Use os valores do modelo.'; }
            else { $row['status']='ready';$row['message']='Pronto para cadastrar.'; }
            if (in_array($row['status'],['ready','skipped'],true)) { $seen[$row['email']]=true; }
            $rows[]=$row;
        }
        return $rows;
    }
    public static function import_csv($raw,$mapping=null) {
        $source=self::import_source($raw);
        return self::import_rows($source,$mapping??self::import_auto_mapping($source['header']));
    }
    private static function import_key() { return 'ccs_import_'.get_current_user_id(); }
    private static function import_store($batch) {
        $key=self::import_key();
        self::require_that(set_transient($key,$batch,HOUR_IN_SECONDS) || get_transient($key)===$batch,'Não foi possível guardar a prévia. Tente novamente.',500);
        return $batch;
    }
    private static function import_read($token) {
        self::require_that(self::import_allowed(),'Sem permissão.',403);
        $batch=get_transient(self::import_key());
        self::require_that(is_array($batch) && ($batch['version']??0)===2 && is_string($token) && isset($batch['token']) && hash_equals($batch['token'],$token),'A prévia expirou ou foi substituída. Envie o CSV novamente.',409);
        return $batch;
    }
    public static function import_prepare($raw) {
        self::require_that(self::import_allowed(),'Sem permissão.',403);
        $source=self::import_source($raw);
        return self::import_store(['version'=>2,'token'=>wp_generate_password(32,false,false),'source'=>$source,'mapping'=>self::import_auto_mapping($source['header']),'stage'=>'mapping','rows'=>[],'done'=>false]);
    }
    public static function import_map($token,$mapping) {
        $batch=self::import_read($token);
        self::require_that(!$batch['done'] && isset($batch['source']),'Envie o arquivo novamente para iniciar outra importação.',409);
        $batch['mapping']=self::import_validate_mapping($mapping,count($batch['source']['header']));
        $batch['rows']=self::import_rows($batch['source'],$batch['mapping']);$batch['stage']='preview';
        $batch['token']=wp_generate_password(32,false,false);
        return self::import_store($batch);
    }
    public static function import_commit($token,$confirmed) {
        self::require_that(self::import_allowed(),'Sem permissão.',403);
        global $wpdb;$lock='ccs_'.md5(DB_NAME.$wpdb->prefix);
        self::require_that((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock))===1,'Outra alteração está em andamento. Tente novamente.',409);
        try {
            $batch=self::import_read($token);
            self::require_that($batch['stage']==='preview','Mapeie as colunas e confira a prévia antes de importar.',409);
            self::require_that($confirmed,'Confirme os cadastros antes de continuar.',400);
            if ($batch['done']) { return $batch; }
            foreach ($batch['rows'] as &$row) {
                if ($row['status']!=='ready') { continue; }
                if (email_exists($row['email'])) { $row['status']='skipped';$row['message']='E-mail já cadastrado; nenhuma alteração.';continue; }
                // Revalidate before creating an account, including the permitted access profiles.
                $birth=self::import_birth_date($row['birth_date']);
                if (!$birth || !in_array($row['role'],self::import_roles(),true)) { $row['status']='invalid';$row['message']='Confira a data de nascimento e o perfil; envie o arquivo novamente.';continue; }
                $password=substr($birth,8,2).substr($birth,5,2).substr($birth,0,4);
                $id=wp_insert_user(['user_login'=>'ccs_'.strtolower(wp_generate_password(24,false,false)),'user_email'=>$row['email'],'display_name'=>$row['name'],'user_pass'=>$password,'role'=>$row['role'],'meta_input'=>['ccs_phone'=>$row['phone'],'ccs_birth_date'=>$birth]]);
                unset($password);
                if (is_wp_error($id)) { $row['status']='failed';$row['message']='Não foi possível cadastrar: '.$id->get_error_message();continue; }
                $row['status']='created';$row['message']='Pessoa cadastrada.';$row['user_id']=$id;
                // Audit only the target ID. Passwords and CSV contents are never logged.
                self::audit('user_imported',0,(string)$id);
            } unset($row);
            $batch['done']=true;unset($batch['source']);
            return self::import_store($batch);
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
    }
    public static function import_template() {
        if (!self::import_allowed()) { wp_die('Sem permissão.', '', ['response'=>403]); }
        check_admin_referer('ccs_import_template');nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="modelo-voluntarios.csv"');
        echo "\xEF\xBB\xBF".'nome;email;data_nascimento;telefone;perfil'."\r\n".'Pessoa Exemplo;pessoa@example.com;07/03/1990;41999999999;voluntario'."\r\n";exit;
    }
    private static function import_mapping_form($batch) {
        $header=$batch['source']['header'];
        echo '<section class="ccs-import-card"><h2>2. Mapear campos</h2><p>Selecione a coluna do arquivo que corresponde a cada campo. Campos com * são obrigatórios. Colunas não selecionadas serão ignoradas.</p><form method="post" action="'.esc_url(self::import_url()).'">';wp_nonce_field('ccs_import_users');
        echo '<input type="hidden" name="import_action" value="map"><input type="hidden" name="token" value="'.esc_attr($batch['token']).'"><div class="ccs-import-mapping">';
        foreach (self::import_fields() as $field=>[$label,$required]) {
            echo '<label for="ccs-map-'.esc_attr($field).'">'.esc_html($label.($required?' *':'')).'<select id="ccs-map-'.esc_attr($field).'" name="mapping['.esc_attr($field).']"'.($required?' required':'').'><option value="">'.($required?'Selecione uma coluna':'Não importar este campo').'</option>';
            foreach ($header as $index=>$title) { echo '<option value="'.esc_attr($index).'"'.selected((string)$batch['mapping'][$field],(string)$index,false).'>'.esc_html(($index+1).' — '.($title?:'Sem título')).'</option>'; }
            echo '</select></label>';
        }
        echo '</div><p>Sem coluna de perfil, todas as novas pessoas serão <strong>Voluntário</strong>. A data de nascimento define a senha inicial em <strong>ddmmaaaa</strong>.</p>';
        submit_button('Validar mapeamento e visualizar');echo '</form><details><summary>Conferir as primeiras linhas do arquivo</summary><div class="ccs-import-scroll"><table class="widefat striped"><thead><tr>';
        foreach ($header as $index=>$title) { echo '<th>'.esc_html(($index+1).' — '.($title?:'Sem título')).'</th>'; }
        echo '</tr></thead><tbody>';
        foreach (array_slice($batch['source']['records'],0,3) as $record) {
            echo '<tr>';foreach (array_keys($header) as $index) { echo '<td>'.esc_html(mb_substr($record['values'][$index]??'',0,100)).'</td>'; }echo '</tr>';
        }
        echo '</tbody></table></div></details></section>';
    }
    public static function import_page() {
        if (!self::import_allowed()) { wp_die('Sem permissão.', '', ['response'=>403]); }
        nocache_headers();$key=self::import_key();$error='';$notice='';
        if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
            check_admin_referer('ccs_import_users');
            try {
                $action=sanitize_key($_POST['import_action']??'');
                if ($action==='upload') {
                    delete_transient($key);
                    $file=$_FILES['csv']??null;
                    self::require_that($file && (int)$file['error']===UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name']),'Selecione um arquivo CSV válido.',400);
                    self::require_that(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))==='csv','Envie um arquivo .csv, exportado em UTF-8.',400);
                    self::require_that((int)$file['size']<=2*1024*1024,'Limite de 2 MB por arquivo.',400);
                    $raw=file_get_contents($file['tmp_name']);self::require_that(is_string($raw),'Não foi possível ler o arquivo.',400);
                    self::import_prepare($raw);
                } elseif ($action==='map') {
                    self::import_map(wp_unslash($_POST['token']??''),wp_unslash($_POST['mapping']??[]));
                } elseif ($action==='commit') {
                    self::import_commit(wp_unslash($_POST['token']??''),isset($_POST['confirm']));
                    $notice='Importação concluída. Confira os resultados por linha.';
                } elseif ($action==='clear') { delete_transient($key); }
            } catch (CCS_Exception $e) { $error=$e->getMessage(); }
            catch (Throwable $e) { $error='Não foi possível concluir a importação. Consulte os resultados e envie novamente o CSV para retomar; e-mails existentes serão ignorados.';
            }
        }
        $batch=get_transient($key);
        // Previews from releases before column mapping must never create accounts with the old rules.
        if (is_array($batch) && ($batch['version']??0)!==2) { delete_transient($key);$batch=false;$notice='Envie novamente o CSV para mapear os campos e incluir a data de nascimento.'; }
        echo '<div class="wrap ccs-import"><h1>Importar pessoas</h1><p>Envie um CSV UTF-8, exportado pelo Excel ou Google Planilhas. Limites: 200 pessoas e 2 MB. Aceita vírgula ou ponto e vírgula.</p>';
        echo '<style>.ccs-import{max-width:1100px}.ccs-import-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin-top:16px}.ccs-import-mapping{display:grid;gap:16px}.ccs-import-mapping label{font-weight:600;display:grid;gap:6px}.ccs-import-mapping select{width:100%;max-width:100%;min-height:40px;font-weight:400}.ccs-import-scroll{max-width:100%;overflow-x:auto;margin-top:12px}.ccs-import input[type=file]{max-width:100%;margin:8px 0}.ccs-import details{margin-top:12px}.ccs-import summary{cursor:pointer}.ccs-import .submit{margin-bottom:0}.ccs-import td,.ccs-import th{overflow-wrap:anywhere;min-width:100px}@media(min-width:600px){.ccs-import-mapping{grid-template-columns:repeat(2,minmax(0,1fr))}}</style>';
        echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=ccs_import_template'),'ccs_import_template')).'">Baixar modelo CSV</a></p>';
        echo '<p>Nome, e-mail e data de nascimento são obrigatórios para novas pessoas. Telefone e perfil são opcionais. Datas: <code>dd/mm/aaaa</code>, <code>aaaa-mm-dd</code> ou <code>ddmmaaaa</code>. Exemplo: <code>07/03/1990</code> gera a senha inicial <code>07031990</code>.</p>';
        echo '<p>Perfis aceitos: <code>voluntario</code> (padrão), <code>lider_de_equipe</code>, <code>lider_de_departamento</code>, <code>pastor</code> ou <code>secretaria</code>. Contas existentes e suas senhas são preservadas. Novas pessoas ficam ativas, sem equipe ou função. Não há envio automático de e-mails nesta importação.</p>';
        if ($error) { echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>'; }
        if ($notice) { echo '<div class="notice notice-success"><p>'.esc_html($notice).'</p></div>'; }
        echo '<section class="ccs-import-card"><h2>1. Enviar arquivo</h2><form method="post" enctype="multipart/form-data" action="'.esc_url(self::import_url()).'">';wp_nonce_field('ccs_import_users');
        echo '<input type="hidden" name="import_action" value="upload"><label for="ccs-import-csv">Arquivo CSV com cabeçalho</label><br><input id="ccs-import-csv" type="file" name="csv" accept=".csv,text/csv" required>';
        submit_button('Enviar e mapear campos');echo '</form></section>';
        if (is_array($batch)) {
            if (!$batch['done'] && isset($batch['source'])) { self::import_mapping_form($batch); }
            if ($batch['stage']==='preview') {
                $counts=array_count_values(array_column($batch['rows'],'status'));$ready=$counts['ready']??0;
                echo '<section class="ccs-import-card"><h2>'.($batch['done']?'Resultado':'3. Conferir prévia').'</h2><p>'.esc_html(sprintf('Prontos: %d · Criados: %d · Ignorados: %d · Inválidos/falhas: %d',$ready,$counts['created']??0,$counts['skipped']??0,($counts['invalid']??0)+($counts['failed']??0))).'</p>';
                echo '<div class="ccs-import-scroll"><table class="widefat striped"><thead><tr><th>Registro</th><th>Nome</th><th>E-mail</th><th>Nascimento</th><th>Telefone</th><th>Perfil</th><th>Resultado</th></tr></thead><tbody>';
                foreach ($batch['rows'] as $row) {
                    echo '<tr>';foreach (['line','name','email','birth_date','phone','role','message'] as $field) {
                        $value=$row[$field];if ($field==='birth_date' && $value) { $value=substr($value,8,2).'/'.substr($value,5,2).'/'.substr($value,0,4); }
                        echo '<td>'.esc_html($value).'</td>';
                    }echo '</tr>';
                }echo '</tbody></table></div>';
                if (!$batch['done'] && $ready) {
                    echo '<form method="post" action="'.esc_url(self::import_url()).'">';wp_nonce_field('ccs_import_users');
                    echo '<input type="hidden" name="import_action" value="commit"><input type="hidden" name="token" value="'.esc_attr($batch['token']).'"><p><label><input type="checkbox" name="confirm" required> Confirmo a criação das pessoas prontas, com os perfis indicados e senha inicial pela data de nascimento (ddmmaaaa). Linhas inválidas e duplicadas serão ignoradas.</label></p>';
                    submit_button('Importar '.$ready.' pessoas');echo '</form>';
                }
                echo '</section>';
            }
            echo '<form method="post" action="'.esc_url(self::import_url()).'">';wp_nonce_field('ccs_import_users');echo '<input type="hidden" name="import_action" value="clear">';submit_button('Limpar arquivo/prévia/resultado','secondary');echo '</form><p>O arquivo, a prévia e o resultado ficam disponíveis por até 1 hora, somente para o administrador que enviou o arquivo.</p>';
        }
        echo '</div>';
    }
}

