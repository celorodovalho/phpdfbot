# Bot de vagas do PHPDF

Bot para Telegram destinado a disponibilização de vagas para área de Tecnologia. Foi criado utilizando PHP, permitindo com integração com API do Telegram.

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

## Como acessar?

- [Telegram](https://t.me/phpdfbot)

## :handshake: Contribuindo

 * Se você perceber que algo esta errado, [abra uma issue no GitHub](https://github.com/php-fig/php-fig.github.com/issues).

 * Você mesmo pode consertar, simplesmente [edite o arquivo no GitHub](https://github.com/blog/905-edit-like-an-ace) e abra um novo pull request. O repositório será atualizado assim que o seu pull request for aceito!

:octocat: :smiley: :zap:

## :registered: Referências

- [PHPDF](https://phpdf.org.br)
- [Padrão de mensagens para commits](https://github.com/devbrotherhood/cmc)
