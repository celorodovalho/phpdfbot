<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SeederConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('config')->insert([
                [
                    'key' => 'rules',
                    'value' => "⚠️ REGRAS ⚠️

1️⃣ Ao entrar no grupo APRESENTE-SE;
2️⃣ 🚫 É proibido 🚫:
       ❌👤 Qualquer tipo de discriminação e racismo
       ❌🔗 Proibido qualquer tipo de corrente
       ❌⚽️ Futebol
       ❌⛪️ Religião
       ❌👨‍⚖️ Política
       ❌🔞 Pornografia
3️⃣ Antes de postar, releia e analise se o conteúdo:
       📌 Não ofende algum dos membros do grupo;
       📌 Tem relevância e interesse para o trabalho da grupo;
       📌 Se o conteúdo tem procedência, só poste se você pode defender a autenticidade do assunto, consulte fontes seguras;
       📌 Seja propositivo, não faça críticas desnecessárias;
       📌 Se precisar chamar a atenção de alguém, faça com mensagens privadas, direto para os responsáveis.
4️⃣ Visite nossos canais:
       📌 Canal de vagas: @VagasBRTI
       📌 Whatsapp (Official): http://bit.ly/phpdf-official
       📌 Whatsapp (Off-Topic): http://bit.ly/phpdf-offtopic"
                ]
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('config')->truncate();
    }
}
