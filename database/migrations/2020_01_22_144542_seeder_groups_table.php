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
                'name' => '@VagasBrasil_TI',
                'main' => true,
                'type' => GroupTypes::TYPE_CHANNEL,
                'tags' => json_encode([]),
            ],
            [
                'name' => '@phpbrasilvagas',
                'main' => false,
                'type' => GroupTypes::TYPE_CHANNEL,
                'tags' => json_encode(['php']),
            ],
            [
                'name' => '@GrupoClubedeVagas',
                'main' => false,
                'type' => GroupTypes::TYPE_CHANNEL,
                'tags' => json_encode([]),
            ],
            [
                'name' => '@phpdf',
                'main' => true,
                'type' => GroupTypes::TYPE_GROUP,
                'tags' => json_encode([]),
            ],
            [
                'name' => '@phpbrasil',
                'main' => false,
                'type' => GroupTypes::TYPE_GROUP,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'clubinfobsb@googlegroups.com',
                'main' => true,
                'type' => GroupTypes::TYPE_MAILING,
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
                'type' => GroupTypes::TYPE_MAILING,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'gebeoportunidades@googlegroups.com',
                'main' => false,
                'type' => GroupTypes::TYPE_MAILING,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'profissaofuturowindows@googlegroups.com',
                'main' => false,
                'type' => GroupTypes::TYPE_MAILING,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'nvagas@googlegroups.com',
                'main' => false,
                'type' => GroupTypes::TYPE_MAILING,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'leonardoti@googlegroups.com',
                'main' => false,
                'type' => GroupTypes::TYPE_MAILING,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'frontendbr/vagas',
                'main' => false,
                'type' => GroupTypes::TYPE_GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'androiddevbr/vagas',
                'main' => false,
                'type' => GroupTypes::TYPE_GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'CangaceirosDevels/vagas_de_emprego',
                'main' => false,
                'type' => GroupTypes::TYPE_GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'CocoaHeadsBrasil/vagas',
                'main' => false,
                'type' => GroupTypes::TYPE_GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'phpdevbr/vagas',
                'main' => false,
                'type' => GroupTypes::TYPE_GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'vuejs-br/vagas',
                'main' => false,
                'type' => GroupTypes::TYPE_GITHUB,
                'tags' => json_encode([]),
            ],
            [
                'name' => 'backend-br/vagas',
                'main' => false,
                'type' => GroupTypes::TYPE_GITHUB,
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
