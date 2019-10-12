# Bot de vagas do @phpdf

## Comandos

* bot:populate:channel {type} {opportunity?} {message?} {chat?}
    * type {obrigatorio}:
        * process: Processo inicial que busca as vagas no diversos canais, cadastra-as no banco de dados depois de sanitiza-las e as envia para aprovacao no grupo de admin
        * approval: Envia a vaga para aprovacao.
        * send: Envia a vaga aprovada para o canal.
        * notify: Envia uma notificao para o grupo de que ha novas vagas publicadas.
    * opportunity {opcional}: O ID da vaga cadastrada
    * message {opcional}: O ID no telegram da messagem enviada
    * chat {opcional}: O ID no telegram da conversa de quem enviou a vaga
    

https://t.me/phpdfbot