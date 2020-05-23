<?php

use App\Enums\GroupTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SeederGroupsTableUpdateMainAndDelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('groups')
            ->where([
                ['main', '=', true],
                ['type', '=', GroupTypes::MAILING]
            ])
            ->update(['main' => false]);

        DB::table('groups')
            ->where([
                ['name', '=', '@GrupoClubedeVagas'],
            ])
            ->update(['deleted_at' => Carbon\Carbon::now()]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
