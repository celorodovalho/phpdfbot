<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Enums\GroupTypes;

class SeederGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('groups')->insert([
            [
                'name' => '@botphpdf',
                'main' => false,
                'admin' => true,
                'type' => GroupTypes::GROUP,
                'tags' => json_encode([]),
            ],
            [
                'name' => '@VagasBrasil_TI',
                'main' => true,
                'admin' => false,
                'type' => GroupTypes::CHANNEL,
                'tags' => json_encode([]),
            ],
            [
                'name' => '@phpbrasilvagas',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::CHANNEL,
                'tags' => json_encode(['php']),
            ],
            [
                'name' => '@GrupoClubedeVagas',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::CHANNEL,
                'tags' => json_encode([]),
            ],
            [
                'name' => '@phpdf',
                'main' => true,
                'admin' => false,
                'type' => GroupTypes::GROUP,
                'tags' => json_encode([]),
            ],
            [
                'name' => '@phpbrasil',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::GROUP,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'clubinfobsb@googlegroups.com',
                'main' => true,
                'admin' => false,
                'type' => GroupTypes::MAILING,
                'tags' => json_encode([
                    'DF',
                    'BSB',
                    'Distrito Federal',
                    'Brasília',
                    'Águas Claras',
                    'Asa Sul',
                    'Asa Norte',
                    'Taguatinga',
                    'Goiânia',
                ]),
            ],
            [
                'name' => 'clubedevagas@googlegroups.com',
                'main' => true,
                'admin' => false,
                'type' => GroupTypes::MAILING,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'gebeoportunidades@googlegroups.com',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::MAILING,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'profissaofuturowindows@googlegroups.com',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::MAILING,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'nvagas@googlegroups.com',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::MAILING,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'leonardoti@googlegroups.com',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::MAILING,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'frontendbr/vagas',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'androiddevbr/vagas',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'CangaceirosDevels/vagas_de_emprego',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'CocoaHeadsBrasil/vagas',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'phpdevbr/vagas',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'vuejs-br/vagas',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'backend-br/vagas',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::GITHUB,
                'tags' => json_encode([]),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('groups')->truncate();
    }
}
