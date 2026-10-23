# CCShalom Escalas — 0.12.0

## Atualização

Faça backup do site e do banco. Atualize em **Escalas → Atualizações → Verificar agora**. Para instalar pelo ZIP, envie `ccshalom-escalas.zip` em **Plugins → Adicionar plugin → Enviar plugin** e escolha substituir a versão instalada. Abra o painel de escalas: a migração é automática e preserva cadastros, datas, respostas, substituições e histórico. Atualize o cache dos arquivos CSS/JavaScript.

As equipes existentes mantêm o modo anterior. Para adotar o novo processo, edite a equipe e escolha **Vagas por função**; cadastre suas funções antes de gerar novas datas. As escalas avulsas equivalentes são agrupadas em um evento, sem reconvocar pessoas nem alterar confirmações e substituições. As novas equipes já usam vagas por função como padrão.

## Instalação inicial

Se a hospedagem desabilitar a função PHP `mail()`, a versão 0.10.2 trata a falha de envio para permitir concluir alterações de senha e e-mail no WordPress. O administrador recebe um aviso sobre a configuração do envio. Configure um serviço SMTP ou outro serviço de e-mail no WordPress para entregar avisos e links de recuperação; a proteção contra erro crítico não entrega mensagens por conta própria. Transportes SMTP e serviços externos já configurados continuam sendo utilizados.

WordPress individual (sem multisite), WordPress 6.4+, PHP 8.0+, MySQL/MariaDB com InnoDB e GET_LOCK. Use HTTPS na hospedagem definitiva. Configure o fuso da igreja em **Configurações → Geral** antes de publicar datas.

Ative o plugin e abra **Escalas → Abrir painel de escalas**. A página criada contém `[ccshalom_escalas]`, normalmente em `/escalas/`, com interface independente do tema. Configure a entrega dos e-mails do WordPress para recuperação de senha.

## Login próprio e cadastro

O endereço da página de escalas passa a mostrar a tela de login do aplicativo quando não há sessão. O voluntário usa **Entrar com biometria** ou e-mail/usuário e senha. Cadastro, recuperação de senha, confirmação da nova senha e saída têm telas e redirecionamentos na mesma página do aplicativo, preservando o caminho do PWA. O wp-login continua disponível para administração e outros recursos do WordPress.

O link **Cadastre-se** abre nome completo, e-mail, telefone com DDD e senha de 12 a 128 caracteres. A conta recebe o perfil **Voluntário**, somente com a capacidade read, equivalente ao assinante padrão. Não habilite o cadastro geral do WordPress para usar este formulário: ele não depende dessa opção.

O cadastro fica ativo imediatamente, sem aprovação ou confirmação de e-mail. O voluntário escolhe suas funções no perfil e a liderança o vincula às equipes; todos os usuários ativos podem acompanhar o evento completo, mas só alteram sua própria participação e as equipes sob sua liderança. Usuários existentes podem receber um perfil de escalas em **Usuários → Editar**.

A tela de recuperação depende da entrega de e-mails do WordPress. Ela apresenta a mesma mensagem para e-mails existentes e inexistentes. Redefinir a senha por recuperação revoga as passkeys da conta: depois de entrar, cadastre novamente os acessos desejados. A primeira entrada de contas criadas pelo pastor pode continuar sendo feita por recuperação de senha.

## Biometria, Face ID ou PIN

1. Entre com sua senha na página de escalas, pelo endereço HTTPS definitivo.
2. Aceite a sugestão de ativação ou abra **Perfil → Acesso com biometria → Ativar neste dispositivo**.
3. Dê um nome ao acesso, confirme sua senha atual e complete a verificação solicitada pelo aparelho.
4. No próximo acesso, use **Entrar com biometria**, sem precisar digitar e-mail e senha. A senha continua disponível como alternativa.

O recurso usa Passkeys/WebAuthn. O navegador/aparelho decide se pede digital, reconhecimento facial ou PIN. O plugin não recebe nem armazena impressões digitais, imagens faciais ou chaves privadas; guarda a chave pública e os dados necessários à verificação. Não é possível obrigar o aparelho a usar somente digital ou somente Face ID.

Requer HTTPS, navegador com WebAuthn, bloqueio de tela configurado e OpenSSL no PHP. Há exceção HTTP apenas para desenvolvimento em localhost. Até dez acessos podem ser cadastrados por conta. O perfil mostra nome, data de cadastro e último uso. **Remover** revoga a chave para novos logins; sessões já abertas continuam até sair ou expirar. O provedor do aparelho pode sincronizar uma passkey entre dispositivos: remover essa chave invalida suas cópias sincronizadas.

As chaves ficam vinculadas ao domínio usado no cadastro. Mudar o domínio requer entrar com senha e cadastrar novamente as passkeys. Use o mesmo domínio e protocolo em WordPress e no PWA. Um cadastro inativo não autentica por passkey. A ativação exige sessão válida e confirmação da senha atual.

## Perfil do voluntário

O perfil apresenta identificação, vínculo de liderança, resumo de equipes/funções/próximas escalas, os departamentos das equipes e as funções às quais o usuário está vinculado. Acesso administrativo a uma equipe não a inclui automaticamente na lista pessoal: aparecem equipes em que a pessoa é integrante ou líder.

Há cartões com e-mail e telefone, acessos por biometria, recuperação de senha e saída. Para alterar dados ou vínculos, a pessoa procura a liderança. Equipes antigas sem funções cadastradas continuam aparecendo, com a indicação de que ainda não há função vinculada.

## Painel de eventos e consulta de equipes

A aba **Gestão** abre em **Painel de eventos**. Também há um atalho no **Início** para os perfis com acesso à Gestão. O painel apresenta seis indicadores: eventos no período, departamentos previstos, equipes a definir, vagas a preencher, respostas pendentes e participações confirmadas. Cada evento aparece uma única vez, com suas pendências e um botão para abrir a programação completa.

Em **Filtrar eventos**, escolha período, mês, departamento e situação. Selecionar um mês muda o período para todos, permitindo consultar meses anteriores. **Limpar filtros** retorna aos próximos eventos publicados. Os indicadores acompanham os filtros. Departamentos são contados sem repetição; participações contam vagas confirmadas, podendo incluir a mesma pessoa em eventos distintos. Vagas a preencher incluem recusas aguardando substituto. Eventos cancelados podem ser consultados, mas não somam pendências ou confirmações. A barra de confirmação considera apenas as vagas já publicadas; equipes ainda não definidas aparecem em indicador separado.

Administradores, pastores e Secretaria veem o panorama geral. Líderes de departamento veem os indicadores dos departamentos sob sua liderança e podem abrir o evento completo. A Secretaria mantém acesso somente para consulta. Voluntários e líderes de equipe continuam acompanhando os eventos no Início e em suas equipes, sem acesso à Gestão. Cadastros e pessoas ficam na subaba **Equipes e cadastros**.

Na aba **Equipes**, cada cartão mostra somente nome da equipe, responsável, departamento e quantidade de integrantes. Toque nele para abrir integrantes, funções e formato de convocação. As ações de editar a equipe e selecionar funções permanecem nos detalhes, conforme as permissões da liderança.

## Publicar um evento e assumir equipes

1. Cadastre os departamentos, seus líderes e as equipes, com líder e integrantes.
2. Em cada equipe, escolha o formato: **Convocar equipe inteira**, **Vaga aberta · 1 pessoa** ou **Vagas por função**. No terceiro formato, selecione funções gerais e quantidades. Os voluntários escolhem suas funções no Perfil e a liderança pode atribuí-las.
3. Em **Gestão → Publicar evento**, informe nome, local, chegada, início e término. Selecione os departamentos necessários e quantas equipes de cada um precisam participar. O padrão é uma equipe. Pode repetir a publicação em até 12 datas.
4. Cada líder encontra o evento no **Início** ou em **Equipes → Datas disponíveis para assumir**. Abre **Ver evento completo**, escolhe **Assumir com [equipe]** e confirma. Só pode assumir as equipes em que é líder; o líder de departamento também pode definir as equipes de seu departamento.
5. A participação é criada com o modo cadastrado da equipe. Equipe inteira convoca todos os integrantes ativos para confirmar. Vaga aberta permite que um integrante escolha a única vaga. Vagas por função permite escolhas por pessoas habilitadas ou delegação pelo líder, já confirmadas.
6. O aplicativo mostra um evento por data, incluindo todos os departamentos, equipes e pessoas designadas. A pessoa confirma, declina, assume ou troca suas próprias vagas. Ver outras participações não concede permissão de edição.
7. Para tirar uma equipe, seu líder usa **Liberar participação da equipe**. Somente suas convocações e trocas pendentes são canceladas. O departamento volta a ter disponibilidade, preservando as demais equipes e o evento.

Exemplo: publique Domingo com Artes e Comunicação. O líder da Música 1 assume Artes; o líder da Transmissão assume Comunicação. Em vagas por função, o guitarrista e o tecladista escolhem suas vagas, ou o líder os designa. Todos veem esses nomes e funções no mesmo evento.

Um departamento sem equipes pode usar **Assumir pelo departamento**. O sistema cria uma participação direta com uma vaga aberta e o líder como integrante inicial. O líder de departamento pode organizar integrantes, funções e formato em Equipes; essas configurações valem para participações futuras. Se o departamento já tiver equipes, a participação deve ser assumida por uma delas.

Administrador/pastor publica envolvendo qualquer departamento. Líder de departamento publica envolvendo apenas os departamentos sob sua liderança. Alterar ou cancelar o evento inteiro exige administrar todos os seus departamentos; o líder de equipe continua restrito à sua equipe. Uma alteração de horário no evento pede nova confirmação aos participantes e invalida convites pendentes, preservando orientações específicas de cada equipe.

## Selecionar integrantes pelo nome

Ao cadastrar ou editar uma equipe, digite parte do nome em **Buscar integrante pelo nome**. A lista é filtrada enquanto você digita, ignorando acentos e maiúsculas. Ao selecionar alguém, o nome aparece em **Selecionados**, acima da busca, e permanece ali quando o filtro muda. Use **×** para retirar uma pessoa e continue pesquisando e adicionando. Pessoas já selecionadas não aparecem duplicadas nos resultados.

Na edição, os integrantes atuais já aparecem selecionados. A lista é alterada no servidor somente ao salvar a equipe. Salvar sem selecionados remove os integrantes da equipe; as designações já publicadas permanecem preservadas. A seleção inclui apenas pessoas ativas que possam servir, mantendo Secretaria fora das opções.

## Quem pode assumir

**Assumir com [equipe]** depende do vínculo de líder no cadastro da equipe. O perfil de administrador, pastor ou líder de departamento, por si só, não libera essa opção para todas as equipes. Esses perfis continuam podendo designar equipes por **Definir equipes deste departamento**, conforme suas permissões de gestão.

Em departamentos sem equipes, **Assumir pelo departamento** aparece para o líder cadastrado do departamento. Em vagas por função já publicadas, **Assumir esta vaga** exige que a pessoa seja integrante da equipe e tenha a função correspondente no perfil. Vagas abertas sem função continuam disponíveis aos integrantes da equipe. As regras são verificadas também na API.

## Modelos de celebração

Em **Gestão → Celebrações**, o pastor/administrador pode continuar cadastrando modelos e gerando datas. A regra **O líder da equipe assume a data** publica o departamento sem escolher a equipe antecipadamente. Também permanecem equipes fixas, rodízio e escolha manual. Cada data gerada aparece como um evento único. Mudar o modelo não altera as datas já publicadas.

## Push e sino de avisos

Instale e ative [Portal Push 0.4.0+](https://github.com/paulommaeda/push-notifications) no mesmo WordPress. O plugin de escalas detecta a API automaticamente; não exige templates ou nova chave no Escalas. As chaves, dispositivos e configurações são do Portal Push.

No aplicativo, cada pessoa abre **Perfil → Configurar notificações push** e escolhe **Ativar neste dispositivo**. O painel oficial do Portal Push permite testar e gerenciar aparelhos. A autorização é solicitada pelo botão. Preferências e bloqueios do Portal Push são respeitados.

Avisos de evento disponível chegam às lideranças dos departamentos convocados. Ao assumir a data, a equipe recebe a convocação ou as vagas. Atribuições, alterações, cancelamentos, recusa e pedidos/respostas de troca geram avisos direcionados, disponíveis no sino. O toque no aviso abre o evento; se for necessário entrar, o login por senha ou passkey preserva o evento de destino.

O Escalas registra avisos na mesma transação das alterações e só cria campanhas no Portal Push depois de salvar. Falha no push não desfaz a escala. Há deduplicação e até dez tentativas, dentro de sete dias. O WP-Cron retoma pendências; mantenha o cron do site funcionando. Campanha enfileirada não significa mensagem entregue. A entrega exige HTTPS, navegador/PWA compatível, permissão e inscrição ativa do aparelho. Sem Portal Push, o sino continua funcionando com o aplicativo aberto.

## Necessidades específicas de uma data

Na escala da equipe, use **Ajustar necessidades por função**, selecione a função e informe o total desejado para aquela data, incluindo vagas ocupadas. Isso não altera o padrão da equipe. Zero remove as vagas livres; vagas ocupadas impedem reduzir o total abaixo da quantidade de pessoas atribuídas.

Vagas retiradas são arquivadas internamente, preservando seu histórico para os relatórios futuros. Renomear uma função ou mudar sua quantidade padrão vale para novas escalas; as já publicadas guardam o nome registrado na publicação. Alterar os integrantes habilitados afeta quem pode assumir vagas abertas, mas não desfaz participações já atribuídas.

## Permissões pela hierarquia

| Vínculo | Acesso |
| --- | --- |
| Administrador / Pastor | Pessoas, departamentos, equipes, funções, celebrações e todas as escalas. |
| Líder de departamento | Gerencia o catálogo geral de funções e equipes/escalas do próprio departamento; define equipes nas datas já publicadas. |
| Líder de equipe | Acompanha a equipe; seleciona funções gerais para a própria equipe; assume a data pela própria equipe; ajusta vagas e delega funções da própria equipe. |
| Voluntário | Consulta o evento completo e suas equipes; escolhe suas funções no perfil, assume vagas e solicita substituição por alguém da mesma função. |

Os campos de liderança delimitam o acesso. Ter apenas o perfil de líder não permite gerenciar outras equipes. O cadastro central de celebrações e a geração de datas são do pastor/administrador.

Nomes de voluntários ativos ficam disponíveis no diretório para substituições. E-mail e telefone de outras pessoas são restritos aos perfis com acesso à Gestão; cada usuário vê seus próprios dados no perfil. O telefone não fica exposto no diretório compartilhado.

## Departamento sempre necessário, equipes alternadas

Marque o departamento como obrigatório. Use **Rodízio: uma equipe por data**, ordenando as equipes, ou **Escolher em cada data**. Um departamento obrigatório pode ficar temporariamente sem equipe: aparecerá uma pendência para a liderança. Isso é diferente de uma equipe convocada com funções ainda sem voluntários.

Equipes fixas participam de todas as datas geradas. No rodízio, a sequência continua entre lotes. A prévia permite trocar uma equipe em uma data. Alterar o modelo afeta somente novas datas.

Trocar equipes numa ocorrência cancela as escalas anteriores daquele departamento nessa data e cria novas escalas. O formulário avisa sobre isso e rejeita alterações quando houve preenchimento de vagas depois de sua abertura. Cancelar uma ocorrência cancela suas escalas e convites pendentes.

## Confirmações, liberação e substituições

O voluntário pode confirmar, informar indisponibilidade ou liberar uma vaga aberta/por função para a equipe. Informar indisponibilidade preserva a atribuição para permitir convidar um substituto; liberar reabre a vaga para escolha.

Após informar indisponibilidade, o responsável convida outro voluntário ativo com a mesma função no perfil, inclusive de outra equipe. A troca só acontece com aceite e sem conflito de horário. Para escalas antigas sem função definida, permanece a seleção de voluntários ativos. A função e o histórico da escala são preservados; não há aprovação adicional da liderança.

Alterar horários de uma escala avulsa por função reabre suas vagas para nova escolha. Escalas ligadas a uma celebração acompanham os horários dela; o pastor pode usar “Editar esta data” na celebração. Mudar horários pede nova confirmação. Um usuário não pode ocupar funções simultâneas com horários sobrepostos, inclusive em equipes diferentes.

## Modos anteriores

Continuam disponíveis **Convocar equipe inteira**, que atribui uma convocação a cada integrante ativo, e **Vaga aberta**, com uma pessoa por equipe/data. As escalas existentes mantêm esses comportamentos. Líderes de equipe não publicam convocação de equipe inteira; isso cabe à liderança do departamento/pastor.

## Identidade e PWA

Em **Escalas** no wp-admin, informe a URL HTTPS do logo branco oficial. Sem logo, aparece CCShalom em texto. Montserrat está incluída localmente, com licença OFL; cores azul-marinho `#000A52`, azul `#001BE1` e branco. Login próprio, cadastro e perfil seguem essa identidade. O CSS de personalização do login nativo do WordPress também foi preservado.

As telas priorizam celular, com campos legíveis, botões grandes, áreas seguras e navegação inferior. O login compacto e as ações de autenticação permanecem no caminho da página do aplicativo. O plugin ainda não instala um PWA: não inclui manifest, service worker, push ou uso offline. Na integração futura, use a página de escalas como entrada e preserve o fluxo de login. Não armazene em cache páginas autenticadas, cadastro, login, API, rotas passkeys ou ações; as respostas do sistema já enviam cabeçalhos para impedir cache. Ajuste também qualquer CDN/cache externo que ignore esses cabeçalhos.

## GitHub e atualizações

O atualizador existente aceita repositório público configurado como `organizacao/repositorio`. Uma release estável deve ter tag de versão e o arquivo `ccshalom-escalas.zip`. O fluxo em `.github/workflows/release.yml` pode montar esse pacote quando o código estiver num repositório. A autorização/política desse repositório continua aplicável. Repositórios privados não são suportados nesta versão. Nenhuma release foi publicada no GitHub por esta entrega.

## Relatórios

A base registra celebração, departamento via equipe, equipe, função, vaga, pessoa atribuída, resposta e histórico de ações. Nesta versão não há tela de relatórios nem registro de presença efetiva no dia: confirmação de participação não equivale a comprovação de comparecimento.

## Dependência incluída

A verificação WebAuthn usa lbuchs/WebAuthn 2.2.0, incluída no ZIP com licença MIT e namespace isolado para evitar conflitos com outros plugins. Não exige Composer na hospedagem. Os desafios expiram, são vinculados ao navegador e usados uma única vez; origem exata, identificação da conta, verificação do usuário e assinatura são conferidas no servidor.


## Editar e excluir — versão 0.7.0

- Departamentos e voluntários: Gestão → Editar. O botão de exclusão fica ao final do formulário.
- Equipes: abra o formulário de edição. Funções gerais: Gestão → Cadastro geral de funções → Editar.
- Escalas: abra a escala para editar ou excluir.
- Celebrações: Gestão → Celebrações → Editar cadastro. Excluir o cadastro não exclui as datas já publicadas.
- Para editar ou excluir uma data publicada e suas escalas, abra “Ver departamentos e equipes”. Alterar horários atualiza as escalas ativas vinculadas, verifica conflitos e exige nova confirmação dos participantes atribuídos.

As permissões de liderança continuam sendo aplicadas pelo servidor. A exclusão exige confirmação e retira o registro do uso ativo, preservando os dados históricos; não há restauração pelo painel nesta versão.
Departamentos com equipes, equipes com escalas ativas ou regras de celebrações e funções com vagas ativas exigem ajustar esses vínculos primeiro. O aviso indica o que precisa ser resolvido.
Excluir um voluntário bloqueia o acesso ao aplicativo, remove os vínculos de equipe/função, revoga passkeys e libera vagas futuras. A conta WordPress permanece para preservar autoria e histórico. Não é permitido excluir a própria conta ou administradores neste painel; líderes precisam ser substituídos antes da exclusão.

Homologue esta atualização em uma cópia do site antes da produção. Veja as limitações dos testes em VALIDACAO.md.


## Funções e trocas — versão 0.7.0

1. Cadastre **Bateria** uma vez no catálogo geral.
2. O voluntário marca **Bateria** em seu perfil, mesmo antes de ter equipe.
3. A liderança o vincula à Música 1 e, se desejar, a outras equipes. Cada equipe seleciona Bateria e define sua quantidade de vagas.
4. Uma vaga aberta aparece aos integrantes com a função correspondente. Não há escolha automática entre vários bateristas: quem assume a vaga fica confirmado; a delegação pelo líder também confirma.
5. Ao selecionar **Não posso participar** em uma vaga por função, abre o modal de troca. Ele lista os demais voluntários ativos que marcaram essa função, incluindo os de outras equipes.
6. O escolhido recebe um pedido no **sino no canto superior direito**. No modal, vê solicitante, função, equipe, celebração, chegada, início, término e local. Pode aceitar ou recusar.
7. O aceite transfere a vaga e confirma o substituto, sem vinculá-lo permanentemente à equipe. O servidor verifica novamente a função, os conflitos de horário e se o pedido continua válido. Até o aceite, a escala continua declinada e aguardando substituto.
8. Cancelar a escala, liberar a vaga ou escolher outro substituto invalida o convite anterior. A recusa mantém a necessidade de substituição.

O sino mostra pedidos pendentes e avisos pessoais, é consultado ao abrir o modal e atualizado a cada 30 segundos com o aplicativo aberto e visível. O push opcional é descrito acima. As alterações de escalas exigem conexão.

Na atualização, funções ativas antigas com nome igual (desconsiderando maiúsculas/minúsculas e espaços nas pontas) são reunidas no catálogo geral. Vínculos, quantidades por equipe e perfis são preservados; as vagas guardam o nome histórico. Nomes diferentes, como “Bateria” e “Baterista”, continuam separados. Participações antigas não são confirmadas retroativamente.
Remover uma função do perfil não cancela escalas já assumidas. Remover a função da configuração da equipe não apaga vagas já publicadas; o líder pode ajustar as necessidades dessas datas separadamente. Excluir uma equipe não exclui suas funções gerais.


## Perfis e atribuição de funções — 0.7.0

| Perfil | Aba Gestão | Atribui funções a pessoas | Participa de escalas |
| --- | --- | --- | --- |
| Administrador | Sim | Sim | Sim |
| Pastor | Sim | Sim | Sim |
| Líder de departamento | Sim | Sim | Sim |
| Líder de equipe | Não | Não; escolhe suas próprias funções | Sim |
| Voluntário | Não | Não; escolhe suas próprias funções | Sim |
| Secretaria | Sim, somente consulta | Não | Não |

Em **Gestão → Pessoas cadastradas → Atribuir funções**, administrador, pastor e líder de departamento podem marcar as funções de uma pessoa ativa. Essa ação não altera o perfil de acesso nem remove escalas já assumidas. A atribuição usa o catálogo geral e permite preparar pessoas ainda sem equipe.

A Secretaria pode consultar todos os dados do sistema de escalas: pessoas e contatos, funções, departamentos, equipes, celebrações, escalas e histórico. Pode gerenciar sua própria senha/passkeys, mas não alterar os cadastros ou participar de escalas. Não recebe permissões administrativas do WordPress.

Para cadastrar Secretaria, o administrador/pastor escolhe **Secretaria (consulta)** no perfil da pessoa. Para converter alguém que já serve, primeiro libere ou cancele suas escalas futuras/em andamento e substitua seus vínculos de liderança. O sistema bloqueia a mudança enquanto essas pendências existirem. Mesmo uma alteração de perfil feita diretamente no WordPress não libera participação para Secretaria.

A Gestão aparece para o perfil Líder de departamento mesmo antes de haver um departamento vinculado. O gerenciamento de equipes e escalas continua restrito aos departamentos sob sua liderança. Líderes de equipe acessam as ferramentas da própria equipe em **Equipes** e nos detalhes das escalas, sem a aba Gestão. A liderança de equipe decorre do vínculo de líder no cadastro, inclusive quando a pessoa mantém o perfil Voluntário. Isso não concede acesso à Gestão.


## Importar pessoas — versão 0.10.1

Apenas administradores do WordPress podem importar. Pastor, líder de departamento, líder de equipe, voluntário e Secretaria não recebem esta permissão pelo perfil de escalas.

1. No WordPress, abra **Escalas → Importar pessoas**, ou use **Gestão → Importar pessoas** no aplicativo.
2. Use sua planilha com ou sem cabeçalho, ou clique em **Baixar modelo CSV**. Substitua a pessoa de exemplo pelos seus dados.
3. Salve como **CSV UTF-8**, com vírgula ou ponto e vírgula. Campos entre aspas são aceitos. XLSX não é aceito diretamente: exporte a planilha como CSV UTF-8.
4. Deixe **A primeira linha contém títulos de colunas** desmarcado quando o arquivo começar diretamente por uma pessoa. Se houver títulos, marque a opção; nenhum nome de coluna específico é exigido. Clique em **Enviar e mapear campos**. Selecione as colunas de nome, e-mail e nascimento pela posição (Coluna 1, Coluna 2...) e pelos exemplos. Telefone e perfil são opcionais. As colunas não selecionadas serão ignoradas. Quando houver cabeçalho, o sistema sugere títulos comuns; revise as sugestões.
5. Clique em **Validar mapeamento e visualizar**. Confira os nomes, nascimentos, e-mails, perfis e avisos. Se necessário, ajuste o mapeamento e gere uma nova prévia. Marque a confirmação e clique em **Importar pessoas**.
6. Mantenha a página aberta enquanto a criação prossegue automaticamente. O progresso é salvo a cada etapa. Se sair da página, volte e clique em **Continuar importação** para retomar. Confira o resultado por linha e use Anterior/Próxima para percorrer a lista. Linhas inválidas não são importadas; corrija-as e envie novamente. E-mails já cadastrados, inclusive fora do plugin, são ignorados e preservados.

| Coluna | Regra |
| --- | --- |
| nome | Obrigatório; até 150 caracteres. |
| email | Obrigatório; válido e exclusivo. Usado para login e recuperação de senha. |
| data_nascimento | Obrigatória para novas pessoas; data real e não futura em dd/mm/aaaa, aaaa-mm-dd ou ddmmaaaa. |
| telefone | Opcional; DDD e 10 a 15 dígitos. Aceita pontuação. |
| perfil | Opcional; vazio significa voluntario. Valores: voluntario, lider_de_equipe, lider_de_departamento, pastor, secretaria. |

A senha inicial de cada nova conta é a data de nascimento em **ddmmaaaa**, com oito dígitos e zeros à esquerda. Exemplo: **07/03/1990 → 07031990**. A pessoa entra com seu e-mail e essa senha. Contas existentes mantêm a senha, o perfil e os contatos, inclusive em reenvios ou confirmações repetidas. Não é possível criar administradores, escolher senhas arbitrárias ou vincular equipes/funções por esse arquivo. Cada nova conta começa ativa; os vínculos com equipes e funções são feitos depois no aplicativo. O plugin não dispara e-mails durante a carga.

**Sem limite de pessoas por arquivo.** O CSV pode ter até **100 colunas e 2 MB**. Arquivo, mapeamento e progresso ficam vinculados ao administrador que os gerou e expiram após 1 hora sem atividade. A lista mostra 100 registros por página; a paginação não limita a quantidade importada. Use **Limpar arquivo/prévia/resultado** para descartar a carga. Enviar outro arquivo substitui o anterior. O CSV não é publicado na biblioteca de mídia.


## Busca de pessoas — 0.8.1
Em **Gestão → Pessoas cadastradas**, digite parte do nome em **Buscar por nome**. A busca ignora acentos e diferenças entre maiúsculas/minúsculas e considera todas as pessoas ativas ou inativas que não foram excluídas. Cada página mostra até 10 pessoas; use **Anterior** e **Próxima**. Alterar a busca volta à primeira página. Os botões de edição e atribuição continuam respeitando as permissões do perfil.


## Atualizações via GitHub — 0.9.0

Repositório oficial: https://github.com/paulommaeda/escalas.

Instale esta versão por ZIP uma vez. Depois, entre em **WordPress → Escalas → Atualizações**. O repositório já vem preenchido; mantenha a consulta habilitada. Use **Verificar agora** e depois **Abrir atualizador de plugins** para instalar uma versão nova.

A opção **Permitir que o WordPress instale novas versões automaticamente** é facultativa. Sem ela, as atualizações ficam disponíveis para instalação manual. Com ela, o WordPress usa seu agendador e as permissões da hospedagem para instalar. Consultas malsucedidas exibem um aviso; nenhuma versão é instalada sem um pacote válido.

O GitHub publica um pacote apenas após os testes do workflow passarem. Um simples commit com a mesma versão não gera uma nova release. O pacote oficial tem o nome ccshalom-escalas.zip e contém a pasta ccshalom-escalas; não utilize o ZIP genérico “Source code” do GitHub para a atualização automática.

