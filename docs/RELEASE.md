## CCShalom Escalas 0.11.0

- Publicação de um evento com data, horários, departamentos necessários e quantidade de equipes.
- Líderes assumem a participação da própria equipe; a convocação usa seu formato: equipe inteira, vaga aberta ou vagas por função.
- Início e Minhas escalas mostram um evento por data, com programação completa e designações de todos os departamentos.
- Ações restritas à própria participação ou liderança. Secretaria permanece em consulta.
- Modelos, equipes fixas e rodízio preservados. Departamentos sem equipes podem organizar participação direta.
- Migração agrupa escalas avulsas equivalentes sem substituir IDs, confirmações, trocas ou histórico.
- Sino com avisos e pedidos de troca, leitura individual e links diretos para o evento.
- Integração com Portal Push 0.4.0+: envio individual após salvar, fila persistente, novas tentativas e deduplicação. Ativação do aparelho no Perfil usando o painel oficial do provedor.

Atualize em **Escalas → Atualizações → Verificar agora**. Para push no celular, ative e configure o **Portal Push 0.4.0+ no mesmo WordPress** e autorize o aparelho no Perfil. Sem ele, os avisos continuam no sino. A entrega pelo navegador depende de HTTPS, permissão do dispositivo e funcionamento do WP-Cron.

Validação automatizada: integração WordPress/MySQL de publicação, modos de equipe, permissões, migração e notificações; API real do Portal Push sem dispositivos reais; interface mobile em Chromium; regressões de importação, atualizador e alteração de senha/SMTP. Não foi feito envio a um celular físico nem alteração do servidor da igreja.
