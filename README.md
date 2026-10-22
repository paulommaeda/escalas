# CCShalom Escalas

Plugin WordPress para equipes de voluntários, celebrações e vagas por função. Interface mobile first, login próprio, passkeys e notificações internas de troca.

## Instalar

Baixe **ccshalom-escalas.zip** na [última release](https://github.com/paulommaeda/escalas/releases/latest) e envie em **Plugins → Adicionar plugin → Enviar plugin**. Para atualizar uma instalação anterior, escolha substituir a versão atual.

## Atualizações pelo GitHub

No WordPress, abra **Escalas → Atualizações**:

1. Repositório: **paulommaeda/escalas** (já configurado por padrão).
2. Mantenha **Habilitar atualizações via GitHub** marcado.
3. Clique em **Salvar configurações** ou **Verificar agora**.
4. Atualize na página **Plugins**. Se desejar, marque a instalação automática.

A consulta usa somente releases estáveis com o arquivo **ccshalom-escalas.zip**. A instalação automática utiliza o agendador nativo do WordPress. Repositórios privados não são suportados.

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

