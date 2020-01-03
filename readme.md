# Bot de vagas do @phpdf

## Requisitos
- PHP 7.1+
- MySQL
- SSL

## Como instalar e utilizar
- git clone git@github.com:php-df/phpdfbot.git
- cp .env.example .env
    - DB_DATABASE: nome do banco de dados
    - DB_USERNAME: usuário do banco de dados
    - DB_PASSWORD: senha do banco de dados
    - TELEGRAM_BOT_NAME: nome do bot no Telegram (https://t.me/BotFather)
    - TELEGRAM_BOT_TOKEN: token do bot criado no Telegram
    - TELEGRAM_OWNER_ID: id do dono do bot no Telegram
    - GOOGLE_PROJECT_ID: id do projeto no Google Console (https://console.developers.google.com/)
    - GOOGLE_CLIENT_ID: id do client no Google Console (pra acesso ao GMail)
    - GOOGLE_CLIENT_SECRET: secret do Google Console
    - GOOGLE_REDIRECT_URI: URL de redirecionamento
    - GITHUB_TOKEN: token do usuário no Github
    - GITHUB_USERNAME: usuário no Github
    - GITHUB_REPO: nome do repositório no Github
    - CLOUDINARY_API_KEY: Chave da API Cloudinary
    - CLOUDINARY_API_SECRET: Secret da API Cloudinary
    - CLOUDINARY_CLOUD_NAME: Nome no Cloudinary
- composer install
- php artisan migrate
- git clone https://github.com/jehy/telegram-test-api
- npm install
- npm start
- Mude a constante \Telegram\Bot\TelegramClient::BASE_BOT_URL para a URL:PORTA do telegram-test-api acima
- Crie uma nova aplicação no https://console.developers.google.com/ e lembre de registrar os mesmos dados nas contantes do .env acima e ao criar a aplicação, lembre-se de utilizar a mesma URL local da instalação desse projeto
- Se inscreva com seu Gmail nos grupos descritos no método \App\Console\Commands\BotPopulateChannel::fetchGMailMessages variável $groups
- No seu navegador, visite a URL do seu projeto local: http://localhost/oauth/gmail e autorize sua Conta do Google a utilizar esse projeto
- Rode os comandos abaixo e/ou escreva seus próprios testes
- Dúvidas: https://github.com/php-df/phpdfbot/issues


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

## Como contribuir?
* Há uma série de logs criados automaticamente e melhorias propostas aqui: https://github.com/php-df/phpdfbot/issues é uma boa forma de começar a contribuir
* Se deseja fazer parte da equipe de administração do projeto, entre em contato aqui: https://t.me/se45ky
    
https://t.me/phpdfbot