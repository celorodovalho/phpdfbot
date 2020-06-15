<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Enums\GroupTypes;

class Seeder0615GroupsTable extends Migration
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
                'name' => '@vagasticbr',
                'main' => false,
                'admin' => false,
                'type' => GroupTypes::CHANNEL,
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
        DB::table('groups')->where('name', '=', '@vagasticbr')->delete();
    }
}
