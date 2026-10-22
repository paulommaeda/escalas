## CCShalom Escalas 0.10.2

- Corrige erro crítico ao alterar senha no WordPress quando a função PHP mail() está indisponível.
- O transporte nativo gera um erro de envio tratável; o salvamento da senha e do e-mail pode concluir normalmente.
- Mantém o envio por SMTP e a integração com serviços externos, sem suprimir notificações quando o envio funciona.
- Mostra um aviso ao administrador para configurar o envio quando o transporte nativo estiver indisponível.
- Validação com a função mail() desabilitada, autenticação com a senha alterada e envio a SMTP local de teste.

Atualize em **Escalas → Atualizações → Verificar agora**. Configure SMTP ou outro serviço de e-mail no WordPress para entregar avisos e recuperação de senha; a proteção contra erro crítico não envia mensagens por conta própria.

