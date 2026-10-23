# Validação — versão 0.14.2

`tests/unlimited-teams.php` exercita os fluxos REST em WordPress/MySQL: publicar sem quantidade de equipes, ignorar limites antigos nos eventos já cadastrados, oferecer a data a outra liderança após a primeira equipe assumir, seleção de 31 equipes, preservação das confirmações existentes, controle de edições simultâneas, restrição ao departamento correto e Secretaria em consulta. Verifica também publicação com 31 equipes a partir de modelo antigo, configuração de 31 equipes fixas e pendência ao liberar uma equipe prevista. A saída de uma equipe não gera falsa pendência quando outro grupo continua participando em um departamento de escolha livre.

`tests/events.cjs` verifica indicadores sem metas numéricas e opções de participação adicional. Em `tests/browser.cjs`, os formulários de celebração e evento são usados sem campos de quantidade, duas equipes são selecionadas e os dados enviados são conferidos. O formulário da celebração é verificado em telas de 360 e 390 pixels. As regressões existentes de rodízio, permissões, convocações, múltiplas funções, tarefas/Kanban, Portal Push, importação, atualizador e SMTP permanecem no workflow.

Publicação condicionada à aprovação completa dos testes: https://github.com/paulommaeda/escalas/actions.

# Validação — versão 0.14.1

`tests/multiple-functions.php` exercita a política de múltiplas atribuições via REST em WordPress/MySQL: convocação e voluntariado em duas funções da mesma equipe, participação em outras equipes/departamentos do evento, entrada de equipe inteira com integrante já designado, confirmação independente de cada função, alteração de horários com todas as atribuições e substituição por alguém que já participa do evento. Mantém recusas para convocar, escolher vaga, confirmar, convidar, aceitar substituição ou publicar equipe inteira em outro evento sobreposto. Verifica transação sem alterações parciais ao falhar uma mudança de horário, intervalos que apenas se encostam e escalas avulsas com occurrence_id=0 sem liberar conflitos entre registros distintos.

O teste de convocações foi atualizado para permitir a mesma pessoa em duas funções. No navegador, a liderança convoca a mesma pessoa para Pregação e Direção do culto e retira somente uma dessas convocações, preservando a outra. As permissões e os controles de concorrência continuam sendo exercitados pelo restante do workflow.

Publicação condicionada à aprovação completa dos testes: https://github.com/paulommaeda/escalas/actions. As limitações anteriores seguem documentadas abaixo.

# Validação — versão 0.14.0

`tests/convocations.php` integra WordPress/MySQL via REST: equipe em formato dirigido com múltiplas funções gerais, necessidades sem convocar todos, ausência de voluntariado para integrante/pastor/administrador, recusa de chamada direta à API, delegação pelo líder sem acesso à Gestão, habilitação e vínculo exigidos, conflitos de horário, chamadas sem token ou desatualizadas, confirmação e aviso individual, reatribuição com cancelamento de convites, retirada confirmada, redução de vagas, função livre acrescentada após a publicação, inativação e aviso ao líder, substituição aceita e nova confirmação preservando o substituto ao mudar horários. Verifica também os formatos preservados nas datas publicadas ao mudar o padrão da equipe.

`tests/convocations.cjs` verifica a apresentação das funções dirigidas, opções exclusivas da liderança, ausência de assumir/liberar pessoalmente, indisponibilidade e troca, Secretaria em consulta e cancelamentos. Em Chromium, `tests/browser.cjs` cria uma função geral adicional, seleciona-a na equipe em formato dirigido, convoca um integrante habilitado, recusa formulário desatualizado, altera/retira convocação e verifica a visão do líder, da pessoa convocada e da Secretaria. Exercita telas de 360, 390 e 768 pixels; a autorização real fica coberta pelos testes REST.

A publicação depende do workflow completo: https://github.com/paulommaeda/escalas/actions. Permanecem as regressões de eventos, tarefas/Kanban, importação, atualizador, Portal Push e SMTP e as limitações de dispositivos físicos documentadas abaixo.

# Validação — versão 0.13.0

A validação inclui `tests/tasks.php` com WordPress/MySQL e chamadas REST reais: classificação por data, publicação e geração de celebrações especiais, migração idempotente, criação/edição/movimentação/conclusão/reabertura/exclusão de tarefas, validação de prazos e responsáveis, vínculo departamento/equipe, atribuição e transferência de acesso, avisos individuais com link, permissões de gestão por departamento e etapa pelo responsável, Secretaria em consulta, conflitos de versão sem sobrescrever nem emitir aviso duplicado, cancelamento/exclusão preservando histórico e isolamento entre datas. A alteração de horários preserva prazos individuais. Bloqueios e transação seguem o mecanismo já utilizado nas escalas.

`tests/tasks.cjs` verifica a seleção de eventos/celebrações, dashboard completo por data, isolamento dos cartões de tarefas, estágios, indicadores de atraso/progresso, apresentação conforme perfil e escape HTML. `tests/browser.cjs` usa JavaScript/CSS reais e API simulada em Chromium para selecionar datas, consultar designações, ativar quadro em uma celebração, criar/editar tarefas, filtrar, mover por botão e arrastar, recuperar conflito, excluir com confirmação e concluir pela visão do responsável. Inclui Secretaria, líder de departamento e voluntário, links diretos e larguras de 360, 390, 768 e 1100 pixels. A autorização real é conferida nos testes REST, independentemente da simulação do navegador.

O workflow completo também executa as regressões WordPress, importação, atualizador, Portal Push e SMTP antes de publicar: https://github.com/paulommaeda/escalas/actions. Não mede alto volume simultâneo nem a entrega em dispositivos físicos; permanecem as limitações documentadas abaixo.

# Validação — versão 0.12.0

`tests/events.cjs` verifica os indicadores consolidados, filtros por mês, período e situação, cancelamentos sem inflar pendências, recusas como vagas a preencher, exclusão de vagas retiradas, departamentos opcionais e o escopo do líder de departamento. Secretaria pode consultar; voluntários não recebem o painel de Gestão.

`tests/browser.cjs` verifica a abertura da Gestão no painel, os seis indicadores, filtros, retorno aos cadastros, acesso da Secretaria sem publicação/alteração e indicadores limitados ao departamento do líder. Na aba Equipes, confere os quatro dados do cartão, ausência da lista de pessoas e funções nessa primeira visão, abertura dos integrantes nos detalhes e continuidade da edição da equipe. Usa JavaScript e CSS reais com API simulada em Chromium, incluindo larguras de 360, 390 e 768 pixels. A autorização no servidor permanece coberta pelos testes REST existentes.

A publicação depende da aprovação do workflow completo, preservando os testes WordPress, Portal Push, importação, atualizador e SMTP: https://github.com/paulommaeda/escalas/actions. Esta atualização não altera cadastros nem designações existentes.

# Validação — versão 0.11.2

O workflow inclui regressões de permissão para o caso de administradores, pastores e líderes de departamento sem liderança da equipe: ausência de opções pessoais de assumir, manutenção da gestão e recusa REST de tentativa direta. Verifica o líder cadastrado, liderança inativa, participação direta de departamento, integrante habilitado em uma função, função incompatível e função compatível sem vínculo com a equipe. Administradores com vínculo explícito de liderança/membro e função continuam podendo participar normalmente.

No navegador, o cadastro e a edição de equipes exercitam busca por nome sem acentos/caixa, seleção acumulada enquanto o filtro muda, remoção/reinclusão sem duplicatas, estado sem resultados, carregamento dos integrantes atuais, prevenção de envio acidental pela busca e o conteúdo enviado ao salvar, inclusive uma seleção vazia. O componente é verificado em 360 e 390 pixels, além dos cenários de eventos em 768 pixels. A integração WordPress confirma que esvaziar os membros não apaga designações publicadas.

A release depende da aprovação do workflow completo, incluindo Portal Push, importação, atualizador e e-mails: https://github.com/paulommaeda/escalas/actions. As limitações de dispositivos físicos e de hospedagem registradas abaixo continuam aplicáveis.

# Validação — versão 0.11.1

O workflow valida sintaxe PHP/JavaScript e executa os testes abaixo antes de publicar a release. Execuções e resultados: https://github.com/paulommaeda/escalas/actions.

`tests/events.php` integra WordPress/MySQL via REST: publicação sem escalas antecipadas, departamentos solicitados, capacidade, repetição sem duplicatas, liderança por vínculo, modos functions/open/roster, delegação, voluntariado, substituição entre equipes, acesso ao evento completo sem exposição de contatos/membros de outras equipes, isolamento das alterações, edição global com nova confirmação, orientação da equipe preservada, seleção de equipes sem reconvocar as mantidas e sem avisar pendências temporárias já resolvidas, participação direta, Secretaria, avisos privados e migração idempotente preservando IDs e histórico. Testes de capacidade sequenciais verificam a recusa da segunda equipe; o bloqueio MySQL serializa as mutações, mas o teste não mede alto volume simultâneo.

`tests/push.php` carrega o Portal Push real, fixado no commit 00b67938ceb0ccd453419961aac7486742f5d4b1 (0.4.0). Confere público individual, limites Unicode, deduplicação mesmo após perda de confirmação, opção de recusa preservada, perfil inativo, falha do provedor após criação da campanha sem desfazer o salvamento, recuperação e limite de tentativas. Não cadastra dispositivos reais nem envia a celulares. A fila do provedor é executada com zero inscrições.

`tests/events.cjs` verifica a renderização unificada e ações conforme permissões. `tests/browser.cjs` usa o JavaScript/CSS reais com API simulada em Chromium: um cartão por evento, programação completa, ações próprias, sino, login por link direto já autenticado, formulário de publicação, liderança e preservação do elemento/listeners do painel de push entre navegações. Verifica ausência de rolagem horizontal em 360, 390 e 768 pixels. A autorização real é validada separadamente pelos testes REST.

Permanecem as regressões de importação, busca/paginação, atualizador, alteração de senha com mail() desabilitada e SMTP local. Os testes não validam entrega de push em aparelho físico, Safari/iOS instalado, permissão real do usuário, configuração do cron da hospedagem ou combinação com todos os plugins de PWA/cache. Não foi alterado o servidor da igreja.

Registros históricos abaixo.

# Validação — versão 0.10.2

A release 0.10.2 é publicada pelo workflow do repositório somente após aprovação das etapas de validação em GitHub Actions, com PHP 8.3, MySQL 8 e WordPress baixado durante a execução. O resultado público está em https://github.com/paulommaeda/escalas/actions.

Regressão do erro crítico com PHP executado com `-d disable_functions=mail`, sem preemptar o envio com um retorno de sucesso fictício. O teste chama wp_update_user, confere a senha no banco e autentica com a senha nova; altera e-mail; verifica o erro real de entrega em retrieve_password; e executa reset_password. A falha nativa gera wp_mail_failed e um aviso administrativo sem conteúdo de mensagens ou senhas. Com mail() disponível, o controle confirma que o mailer não é substituído. Também são preservados serviços que interceptam pre_wp_mail, classes de mailer personalizadas e configurações públicas do mailer padrão.

Um servidor SMTP de teste recebe mensagens somente em 127.0.0.1, sem encaminhamento externo. O teste verifica a entrega ao servidor local e o aviso de troca de senha via SMTP mesmo com mail() desabilitada. Não utiliza credenciais reais nem comprova a entrega pelo provedor da igreja. A proteção converte a falha em erro tratável; o envio efetivo exige SMTP ou outro serviço de e-mail configurado.

Cobertura de CSV sem cabeçalho por padrão, primeira pessoa preservada, arquivo com uma pessoa, BOM/linhas vazias/aspas, mapeamento por posição e exemplos e cabeçalho opcional. O parser aceita 1.001 registros; o teste integrado cria 201 usuários em requisições sucessivas, exercita a continuação pelo controlador administrativo com nonce, verifica a senha de nascimento e a preservação de uma senha alterada durante a carga, percorre a última página de resultados e repete a confirmação sem duplicar contas.

A importação é exercitada em WordPress/MySQL com colunas arbitrárias e duplicadas, aliases, CSV com vírgula/ponto e vírgula, colunas ignoradas, campos opcionais, datas e anos bissextos, limites e dados inválidos. O teste cria usuários, verifica os hashes e o login com a senha ddmmaaaa, os perfis e metadados, e a preservação de contas existentes, inclusive contas criadas após a prévia. Confirmação repetida não recria usuários nem troca a senha. Também verifica permissões, tokens e isolamento por administrador, prévias expiradas/antigas e a saída HTML da tela de mapeamento.

A rotina verifica sintaxe de todos os arquivos PHP e JavaScript; busca/paginação de pessoas; criação das tabelas e repetição da ativação; permissões dos perfis; cadastro e atribuição de funções; publicação/delegação; bloqueio de participação da Secretaria; troca entre equipes com aceite do destinatário; leitura e validação de CSV; integração do atualizador com o mecanismo nativo do WordPress, rejeição de URLs externas e pré-releases.

O teste do atualizador usa respostas controladas do GitHub. O arquivo instalável é empacotado automaticamente com a pasta ccshalom-escalas e suas dependências. Os testes não simulam a instalação automática por WP-Cron, acesso ao servidor real da igreja, todos os plugins de segurança/cache ou biometria em aparelho físico.

As anotações abaixo são históricas, referentes às versões anteriores e às limitações do ambiente existente naquelas datas.

# Validação — versão 0.8.1

25/09/2026: sintaxe JavaScript validada. Verificadas em execução isolada da função real: primeira/última página de uma lista com 23 pessoas; ajuste de página fora do limite; busca por parte do nome sem acentos e diferenças de caixa; nenhum resultado; exclusão de pessoas removidas. Não foi executada revisão visual em navegador. Busca e paginação operam sobre os dados já carregados; esta alteração não reduz o volume da API nem altera as permissões do servidor.

Registros das versões anteriores abaixo.

# Validação — versão 0.8.0

25/09/2026: sintaxe JavaScript validada. Passaram 21 verificações isoladas de interface de perfis/importação, 25 do fluxo de funções/trocas e 20 de formulários. O novo atalho aparece apenas quando o servidor fornece a URL administrativa autorizada.

Revisão de código da importação: capability manage_options e exclusão de Secretaria; nonce no envio/confirmação/modelo; upload temporário; limites de arquivo/linhas; CSV UTF-8; validação de e-mail, telefone e perfil; dados de prévia guardados no servidor, vinculados ao administrador; confirmação com token; e-mails existentes ignorados; bloqueio compartilhado com as ações do aplicativo; saída HTML escapada; senha aleatória não exibida; ausência de envio próprio de e-mails.

**Não executado:** PHP, parser CSV em runtime, criação de usuários, download do modelo, WordPress/MariaDB e navegador real. O ambiente necessário continua indisponível. Teste em homologação antes da produção.

Roteiro recomendado: importar duas pessoas com perfis distintos; conferir telefone/perfil e primeiro acesso; repetir CSV sem duplicar contas; validar vírgula/ponto e vírgula, acentos e BOM; testar e-mail existente fora do plugin, e-mail repetido, perfil administrator, telefone inválido, arquivo excessivo, prévia vencida e confirmação reenviada; verificar recusa para pastor sem manage_options, líder e Secretaria. Substitua a linha fictícia do modelo antes da carga real.

Registros anteriores abaixo.

# Validação — versão 0.7.0

25/09/2026: passaram 18 verificações isoladas de interface de permissões, 25 verificações do fluxo de funções/trocas e 20 dos formulários de edição/exclusão, além da sintaxe JavaScript.

Os novos cenários verificam acesso à Gestão conforme flags do servidor, consulta da Secretaria sem botões de alteração, exclusão da Secretaria das listas de pessoas elegíveis e formulário específico de atribuição sem campo de perfil. Revisão do backend: separação entre acesso ao aplicativo e participação; bloqueio central de alterações da Secretaria; filtros de convocação/substituição; autorização para atribuir funções e vínculos de liderança.

**Limitação:** não foi possível executar PHP/WordPress/MariaDB nem testar em navegador real nesta sessão. Os testes de interface usam dados simulados; não comprovam a autorização do servidor. Homologue a atualização em uma cópia do site.

Confira com uma conta de cada perfil: Gestão visível apenas para os perfis previstos; atribuição por administrador/pastor/líder de departamento; consulta total da Secretaria; rejeição via API de alteração e participação da Secretaria; impossibilidade de convocá-la ou convidá-la para substituir; preservação do acesso por senha/passkey.

Resultados anteriores abaixo.

# Validação — versão 0.6.0

25/09/2026: sintaxe JavaScript validada; passaram 25 verificações isoladas do novo fluxo e 20 dos formulários da versão anterior. Os novos testes exercitaram catálogo sem vínculo de equipe, escolha no perfil antes de ter equipe, funções/quantidades por equipe, candidatos à troca da mesma função incluindo outra equipe, exclusão de usuário inativo e do solicitante, contador do sino, resumo e ações no modal, estado vazio e escape de conteúdo.

Revisão de código: delegação confirmada, validação de função no envio e no aceite, conflitos no aceite, proteção de destinatário, reutilização do bloqueio/transação das ações, filtro de notificações por usuário e descarte de pedidos cancelados/expirados. Migração reúne funções e mantém IDs das vagas e nomes históricos.

**Limitação:** PHP/WordPress/MariaDB e navegador de testes continuam indisponíveis nesta sessão. Não houve execução do backend, migração real nem revisão visual desta versão. Os testes isolados não substituem homologação em cópia do site.

## Roteiro de homologação
1. Atualizar uma cópia com duas equipes que tenham a função Bateria e membros distintos. Verificar catálogo único, quantidades por equipe e histórico preservado. Recarregar sem duplicações.
2. Cadastrar novo voluntário; selecionar Bateria no perfil sem equipe; confirmar ausência de vagas de equipes não vinculadas.
3. Vincular a uma ou mais equipes; publicar vagas, assumir uma e verificar confirmação e bloqueio de conflito.
4. Delegar outra vaga a um membro habilitado; verificar confirmação imediata.
5. Declinar e convidar baterista de outra equipe. Conferir que tecladistas e inativos não aparecem.
6. Entrar como destinatário; abrir sino; aceitar e verificar transferência/confirmado nas duas contas. Repetir com recusa.
7. Cancelar convite/escala, retirar função do destinatário e criar conflito antes do aceite; verificar recusas apropriadas e ausência de transferência indevida.
8. Conferir que outro usuário não pode responder ao convite e que voluntário não edita catálogo nem equipe.
9. Verificar login, passkeys, perfil, modal e navegação em celular/PWA e limpar cache de CSS/JavaScript.

Abaixo, registros de versões anteriores, que não validam os fluxos novos.

# Validação — versão 0.5.0

Em 25/09/2026, passaram a verificação de sintaxe JavaScript e 20 verificações isoladas dos formulários: botões de exclusão apenas para registros existentes, confirmação obrigatória nos sete alvos (incluindo datas de celebração), identificação do item, escape de texto e campos de edição da data.

Revisados os bloqueios de dependência, permissões no servidor, exclusão lógica e proteção contra reativação de escala excluída pelo cancelamento. Os cenários de integração PHP foram preparados, mas não executados: o ambiente temporário com PHP/WordPress/MariaDB da versão anterior já não estava disponível, e não foi possível baixar o runtime novamente. Portanto esta versão não tem validação integrada nem nova revisão visual em navegador. Homologação em cópia do site é necessária antes da produção.

Os resultados abaixo pertencem exclusivamente à versão anterior; não comprovam os novos fluxos de edição/exclusão.

# Validação — versão 0.4.0

Validada localmente com WordPress 7.1.2, PHP 8.3, MariaDB 10.11 e Chromium em 24–25/09/2026. Sem acesso ou alteração do site da igreja.

## Dados existentes

A base de teste foi populada pelos testes anteriores (62 do fluxo original, 89 de celebrações e 44 de funções). A migração 0.3.0 → 0.4.0 preservou integralmente as linhas das onze tabelas anteriores e a página do aplicativo. A nova tabela de passkeys foi criada separadamente. A migração repetida não duplicou eventos. O teste de disputa por uma vaga continuou com exatamente um sucesso e uma recusa.

## Autenticação e interface

Passaram 24 cenários de navegador. A biometria foi simulada por um autenticador virtual CTAP2 com chave residente e verificação de usuário, produzindo assinaturas WebAuthn verificadas pelo código PHP incluído:

- Login próprio por senha, mensagem de erro e sugestão inicial de ativação.
- Perfil mostrando somente equipes pessoais e funções vinculadas, sem confundir visibilidade administrativa com participação.
- Confirmação da senha para cadastrar uma passkey; cadastro e entrada sem e-mail/senha.
- Logout no aplicativo; último uso e revogação da chave.
- Desafios de cadastro/login não reutilizáveis e vinculados ao navegador que os iniciou.
- Rejeição de assinatura falsa, origem adulterada, identificação de usuário incompatível, ausência de verificação do usuário e requisição de outra origem.
- Usuário inativo impedido de entrar com passkey; um pastor não pode usar a API de perfil para remover chave de outra conta.
- Recuperação e redefinição de senha nas telas próprias, com revogação das passkeys anteriores.
- Escalas anteriores acessíveis após a mudança de autenticação.
- Nenhuma exceção JavaScript capturada.

Testes adicionais verificaram cadastro na própria rota do aplicativo, recuperação enquanto autenticado, largura da navegação desktop e saída sem passar por wp-admin/wp-login.

Telas de login e perfil revisadas visualmente em 320, 390 e 1280 pixels. Arquivos PHP e JavaScript passaram na verificação de sintaxe. A dependência lbuchs/WebAuthn 2.2.0 acompanha sua licença MIT e usa namespace isolado.

## Limites

Não foi usado um celular físico nem validado Face ID/Touch ID real, Android, Safari ou um PWA instalado. Os testes usam autenticador virtual em localhost; a hospedagem real precisa de HTTPS e OpenSSL. O envio de recuperação foi capturado localmente, sem teste de SMTP real. Não foram testadas combinações com plugins de segurança/SSO/2FA, CDN/cache, alto volume ou todas as versões mínimas declaradas.

O recurso de passkeys é um método próprio de autenticação e não foi integrado a etapas adicionais de plugins externos de 2FA/SSO. A instalação de PWA/service worker continua sendo responsabilidade da configuração de PWA do site; esta versão não cria um segundo service worker nem oferece acesso offline aos dados privados.

