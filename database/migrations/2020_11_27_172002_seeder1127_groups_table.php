<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Seeder1127GroupsTable extends Migration
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
                'name' => '-1001313694823',
                'main' => false,
                'admin' => false,
                'type' => \App\Enums\GroupTypes::LOG,
                'tags' => json_encode([]),
            ],
            [
                'name' => '@ITJOBS_EMPREGOSTI',
                'main' => false,
                'admin' => false,
                'type' => \App\Enums\GroupTypes::GROUP,
                'tags' => json_encode([]),
            ],
            [
                'name' => '-1001481274775',
                'main' => false,
                'admin' => false,
                'type' => \App\Enums\GroupTypes::GROUP,
                'tags' => json_encode([]),
            ],
            [
                'name' => '@vagas_consultoria_TI',
                'main' => false,
                'admin' => false,
                'type' => \App\Enums\GroupTypes::GROUP,
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
        DB::table('groups')->where('name', '=', '-1001313694823')->delete();
        DB::table('groups')->where('name', '=', '@ITJOBS_EMPREGOSTI')->delete();
    }
}
