=== CCShalom — Escalas ===
Contributors: ccshalom
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.11.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cadastro de voluntários, celebrações, equipes e vagas por função.

== Instalação ==
1. Envie o ZIP em Plugins > Adicionar plugin > Enviar plugin.
2. Para atualizar, substitua a versão instalada. A migração preserva os registros.
3. Abra Escalas > Abrir painel de escalas.
4. Cadastre funções gerais. Voluntários escolhem suas funções no perfil; líderes vinculam pessoas e funções às equipes.
5. Em Gestão > Publicar evento, escolha a data e os departamentos necessários. Lideranças assumem a participação das equipes.
6. Voluntários escolhem vagas em Minhas escalas > Vagas abertas.

Guia completo: docs/GUIA-DE-INSTALACAO.md.

== Changelog ==
= 0.11.1 =
Aviso de atualização de equipes reflete a seleção final, sem sinalizar uma pendência temporária já resolvida.
Mantém confirmações das equipes preservadas e os avisos individuais de cancelamento das equipes retiradas.

= 0.11.0 =
Evento único por data com todos os departamentos, equipes e designações.
Publicação por departamentos; líderes assumem datas seguindo o modo de convocação da equipe.
Quantidade de equipes necessária, participação direta de departamentos sem equipes e manutenção de modelos/rodízio.
Visão completa para voluntários, com alterações restritas à própria participação e liderança.
Migração agrupa escalas avulsas anteriores, preservando vagas, confirmações, trocas e histórico.
Sino com avisos pessoais e pedidos de troca; integração opcional com Portal Push 0.4.0+.
Ativação de dispositivo no perfil, links diretos para o evento e fila de push com tentativas e deduplicação.

= 0.10.2 =
Corrige erro crítico ao alterar senha quando a função mail() está indisponível na hospedagem.
Falha do transporte nativo retorna erro de envio; alteração de senha pode concluir normalmente.
SMTP e serviços externos de envio são preservados; aviso administrativo orienta configurar o envio.
Regressão com mail() desabilitada, autenticação e SMTP local de teste.

= 0.10.1 =
Removido o limite de 200 pessoas por arquivo.
CSV com ou sem cabeçalho: mapeamento por posição e exemplos de valores.
Sem cabeçalho por padrão, preservando a primeira pessoa do arquivo.
Criação progressiva com continuação automática e retomada, sem limite de pessoas por carga.
Prévia e resultado paginados; mantidos o tamanho máximo de 2 MB e a senha de nascimento.

= 0.10.0 =
Importação com mapeamento de colunas, sugestão automática e prévia antes de confirmar.
Data de nascimento obrigatória para novas pessoas e senha inicial no formato ddmmaaaa.
Contas existentes preservadas, incluindo senha, perfil e contatos.
Modelo CSV atualizado; validação de datas reais, campos e mapeamentos.

= 0.9.0 =
Tela de atualizações via GitHub: repositório, verificação manual, status e instalação automática opcional.
Repositório padrão paulommaeda/escalas e publicação de releases com ZIP instalável.
Inclui busca por nome e paginação de pessoas.

= 0.8.1 =
Busca por nome em Gestão > Pessoas cadastradas, sem diferenciar acentos ou maiúsculas.
Paginação de 10 pessoas, com contador e navegação Anterior/Próxima.

= 0.8.0 =
Importação de pessoas por CSV UTF-8 exclusiva para administradores.
Modelo CSV, prévia por linha, confirmação e resultado da carga.
Até 200 pessoas por arquivo, com perfil opcional; e-mails existentes preservados.
Atalho na Gestão e menu Escalas > Importar pessoas no WordPress.

= 0.7.0 =
Gestão por perfil: administrador, pastor, líder de departamento e Secretaria.
Administrador, pastor e líder de departamento atribuem funções às pessoas.
Secretaria consulta todos os departamentos, equipes, pessoas, celebrações e escalas.
Secretaria não altera cadastros, assume vagas, recebe convocações ou substitui voluntários.
Líder de equipe e voluntário não veem Gestão; liderança da equipe permanece na aba Equipes.

= 0.6.0 =
Funções gerais compartilhadas pelas equipes, com escolha pelo voluntário no perfil.
Seleção das funções e quantidade de vagas em cada equipe.
Atribuição pelo líder já confirmada; trocas limitadas à mesma função, inclusive entre equipes.
Sino com pedidos de troca e resumo em modal, aceite e recusa; atualização com o aplicativo aberto.
Migração das funções antigas e dos vínculos, reunindo nomes iguais.
Sem implementação de push. Validação integrada WordPress pendente; veja docs/VALIDACAO.md.

= 0.5.0 =
Edição e exclusão de escalas, equipes, departamentos, funções, voluntários e celebrações.
Exclusão confirmada com preservação de histórico e bloqueios por dependências.
Edição de datas publicadas com atualização das equipes e nova confirmação quando muda o horário.
Edição de e-mail e telefone dos voluntários.
Validação desta atualização: formulários e sintaxe JavaScript; homologação WordPress pendente.

= 0.4.0 =
Login próprio mobile, recuperação de senha e logout dentro do aplicativo.
Passkeys/WebAuthn: ativação, acesso sem senha, gestão e revogação no perfil.
Novo perfil com equipes, funções, liderança, resumo pessoal e dados de contato.
Dependência MIT incluída, sem Composer. Exige HTTPS para biometria.

= 0.3.0 =
Acesso ao painel exige login. Cadastro público com nome, e-mail, telefone e senha.
Perfil Voluntário com apenas leitura, equivalente ao assinante padrão.
Funções por equipe, integrantes habilitados e quantidade de vagas.
Voluntários assumem funções; líderes delegam e ajustam necessidades por data.
Migração mantém escalas e respostas anteriores.

= 0.2.0 =
Cadastro de celebrações, departamentos obrigatórios, equipes fixas, rodízio,
prévia por data, escolha manual de equipes e acompanhamento de pendências.
Modo de vaga aberta para um integrante por equipe/data, com escolha e liberação.
Líderes de equipe podem publicar vagas abertas próprias.
Migração automática e preservação das escalas anteriores.

= 0.1.0 =
Painel mobile, login personalizado, cadastros, escalas recorrentes, conflitos,
confirmações, substituições e atualização por GitHub público.

