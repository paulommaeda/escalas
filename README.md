# CCShalom Escalas

Plugin WordPress para eventos com departamentos, equipes de voluntários e vagas por função. Interface mobile first, login próprio, passkeys, sino de avisos e integração com Portal Push.

## Instalar

Baixe **ccshalom-escalas.zip** na [última release](https://github.com/paulommaeda/escalas/releases/latest) e envie em **Plugins → Adicionar plugin → Enviar plugin**. Para atualizar uma instalação anterior, escolha substituir a versão atual.

## Atualizações pelo GitHub

No WordPress, abra **Escalas → Atualizações**:

1. Repositório: **paulommaeda/escalas** (já configurado por padrão).
2. Mantenha **Habilitar atualizações via GitHub** marcado.
3. Clique em **Salvar configurações** ou **Verificar agora**.
4. Atualize na página **Plugins**. Se desejar, marque a instalação automática.

A consulta usa somente releases estáveis com o arquivo **ccshalom-escalas.zip**. A instalação automática utiliza o agendador nativo do WordPress. Repositórios privados não são suportados.

## Eventos e equipes

Em **Gestão → Gestão de evento**, selecione o evento ou a data de uma celebração. O dashboard daquela data mostra departamentos, equipes com data assumida, vagas abertas, respostas e participações confirmadas. Consulte as designações de cada equipe ou abra a programação para as alterações autorizadas. Os filtros ajudam a encontrar a data; o painel selecionado mostra o evento completo.

Marque **Evento especial · tarefas e Kanban** ao publicar ou em **Configurar tarefas** no painel da data. Crie tarefas com responsável, prazo, descrição, departamento e equipe opcionais. O quadro usa **A fazer**, **Em andamento** e **Concluído**, com indicadores de atraso e progresso. No celular, use os botões; no computador, também pode arrastar os cartões. O responsável acompanha e atualiza a etapa em **Minhas escalas → Minhas tarefas**, sem ganhar acesso à Gestão. Administradores/pastores organizam o evento; líderes de departamento alteram suas tarefas; Secretaria consulta. Avisos usam o sino e a integração Portal Push já configurada.

Na aba **Equipes**, os cartões mostram apenas nome, responsável, departamento e quantidade de integrantes. Toque no cartão para consultar as pessoas e funções ou usar as ferramentas de liderança disponíveis para sua conta.

Em **Gestão → Publicar evento**, informe a data, os horários e os departamentos necessários, com a quantidade de equipes de cada um. Não é preciso convocar antecipadamente cada equipe. O líder abre o evento ou a aba **Equipes** e assume a participação com sua equipe.

A convocação segue o padrão da equipe: **equipe inteira**, **vaga aberta para uma pessoa**, **vagas por função** ou **convocação por função pela liderança**. Todos os usuários ativos veem o evento completo, incluindo os demais departamentos e suas designações. Cada pessoa altera a própria participação; líderes atuam nas equipes/departamentos sob sua responsabilidade. A Secretaria continua somente em consulta.

Na edição de equipes, busque integrantes pelo nome e acumule as escolhas em **Selecionados**, acima do filtro, antes de salvar. O botão de assumir uma equipe depende da liderança cadastrada; as ferramentas de gestão continuam disponíveis aos perfis autorizados. Vagas por função exigem vínculo com a equipe e a função correspondente.

Para equipes como a Pastoral, escolha **Convocação por função pela liderança** no cadastro da equipe. Cadastre livremente as funções gerais (Pregação, Direção do culto, Ceia etc.), selecione-as na equipe e habilite os integrantes nas funções correspondentes. No evento, a liderança usa **Convocar integrante**; as vagas não aceitam voluntariado. A atribuição fica confirmada, com aviso à pessoa designada. A liderança pode alterar ou retirar convocações; indisponibilidade e troca pela mesma função continuam disponíveis. Alterar o formato da equipe vale para novas participações e preserva as datas publicadas.

As escalas avulsas antigas com mesmo nome, local, horários e situação são agrupadas automaticamente, sem substituir vagas, confirmações ou trocas. Modelos de celebração, equipes fixas e rodízio continuam disponíveis.

## Notificações no celular

Ative o [Portal Push 0.4.0 ou posterior](https://github.com/paulommaeda/push-notifications) **no mesmo WordPress** e configure-o. Em **Perfil → Configurar notificações push**, cada pessoa autoriza seu aparelho. A integração envia avisos individuais de publicação, convocação, atribuição, mudanças e trocas usando a API e a fila do plugin existente. O sino funciona mesmo sem o Portal Push.

A fila respeita os dispositivos e as preferências do Portal Push; registrar uma campanha não comprova entrega. HTTPS, suporte do navegador/PWA e processamento regular do WP-Cron são necessários para a entrega em segundo plano. O recebimento não é ativado sem a ação do usuário.

## E-mails e alterações de senha

Na versão 0.10.2, hospedagens com `mail()` indisponível recebem uma falha de envio tratada pelo WordPress, permitindo concluir alterações de senha e e-mail sem erro crítico. Um aviso é mostrado ao administrador. Configure SMTP ou outro serviço de e-mail para entregar notificações e recuperação de senha. Serviços existentes são preservados; a proteção não substitui um serviço de envio.

## Importar pessoas

Em **Escalas → Importar pessoas**, envie um CSV UTF-8 com ou sem cabeçalho e escolha a coluna de cada campo pela posição e pelos exemplos. Por padrão a primeira linha já é uma pessoa; marque **A primeira linha contém títulos de colunas** somente se houver cabeçalho. Os títulos e a ordem podem variar. Nome, e-mail e nascimento são obrigatórios; telefone e perfil são opcionais. Confira a prévia antes de confirmar a criação.

Não há limite de pessoas por arquivo. O CSV pode ter até 2 MB e 100 colunas. A criação prossegue automaticamente em etapas, com progresso salvo e prévia/resultado paginados. Mantenha a página aberta; se sair, volte à importação e clique em **Continuar importação** para retomar a carga pendente dentro de 1 hora.

A senha inicial das novas contas é a data de nascimento em **ddmmaaaa**: 07/03/1990 gera 07031990. Datas inválidas, ausentes ou futuras impedem o cadastro. Aceita dd/mm/aaaa, aaaa-mm-dd e ddmmaaaa. Contas existentes são ignoradas, preservando senhas e perfis. O modelo atualizado está disponível na própria tela.

## Desenvolvimento e publicação

O código do plugin fica na raiz deste repositório. Cada nova versão deve alterar o cabeçalho e a constante em ccshalom-escalas.php, o Stable tag em readme.txt e as notas em docs/RELEASE.md.

Um push em **main** executa validação PHP/JavaScript e testes de integração WordPress/MySQL. Se todos passarem e a versão ainda não existir, o workflow publica a tag e a release com o ZIP instalável. Releases existentes não são sobrescritas.

Para gerar o pacote localmente, execute: python3 scripts/package.py

Saída: dist/ccshalom-escalas.zip, com a pasta correta do plugin. Credenciais de testes no workflow são fictícias e usadas exclusivamente no banco temporário da execução.

Guia: [docs/GUIA-DE-INSTALACAO.md](docs/GUIA-DE-INSTALACAO.md).
Validação e limitações: [docs/VALIDACAO.md](docs/VALIDACAO.md).

