## CCShalom Escalas 0.11.2

- Busca por nome no cadastro e edição de equipes, atualizando os resultados enquanto você digita. Ignora acentos e diferenças entre maiúsculas e minúsculas.
- Integrantes selecionados permanecem no topo ao mudar o filtro. É possível remover uma seleção e continuar adicionando pessoas antes de salvar.
- Edição carrega os integrantes atuais; o salvamento envia exatamente a seleção final, inclusive uma lista vazia.
- “Assumir com [equipe]” aparece apenas para o líder cadastrado da equipe. Administrador, pastor e líder de departamento mantêm as ferramentas de gestão, sem receber todas as equipes como opções pessoais de participação.
- “Assumir pelo departamento” exige o vínculo de líder do departamento. Vagas por função continuam disponíveis apenas a integrantes com a função correspondente.
- As mesmas regras são verificadas pelo servidor. Mantidos os modos de convocação e as designações já publicadas.

Atualize em **Escalas → Atualizações → Verificar agora**. Não é necessário refazer os vínculos ou as escalas existentes.

Testes no workflow: permissões REST e visibilidade das opções por vínculo/função; busca, seleção acumulada, remoção e salvamento de integrantes em Chromium/mobile; regressões WordPress, Portal Push, importação, atualizador e SMTP.
