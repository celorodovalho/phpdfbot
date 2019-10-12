## Como contribuir?
Infelizmente, o Telegram nao dispoe de uma sandbox ou ambiente de desenvolvimento, portanto, nao possibilidade de testar a nao ser em producao.
Para contribuir, realize o clone desse projeto:
<pre>
git clone https://github.com/celorodovalho/phpdfbot.git
</pre>
Mude para a branch de desenvolvimento:
<pre>
git checkout develop
</pre>
Instale o projeto via composer:
<pre>
cp .env.example .env
composer install
</pre>
Esse projeto utiliza a metodologia "git flow" (https://danielkummer.github.io/git-flow-cheatsheet/index.pt_BR.html), portanto, crie uma branch a partir da branch de desenvolvimento:
<pre>
git flow feature start NOME_DA_FEATURE
</pre>
Faca o commit e um PULL REQUEST para a develop.


Dados importantes que so estao disponiveis no ambiente de producao:
<pre>
TELEGRAM_BOT_NAME=phpdfbot
TELEGRAM_BOT_TOKEN=000000000:XXXXXXXxxxxxXXXXXXXXXXXXXXXXXXxxxx
TELEGRAM_CHANNEL=@phpdfvagas
TELEGRAM_GROUP=@phpdf
TELEGRAM_OWNER_ID=
TELEGRAM_GROUP_ADM=
</pre>


## Logs
Os logs de erros sao enviados automaticamente para o grupo de administracao do Bot.