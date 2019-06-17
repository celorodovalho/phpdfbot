<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOpportunitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 100);
            $table->string('position', 50)->nullable();
            $table->text('description', 2500);
            $table->string('salary', 50)->nullable();
            $table->string('company', 50)->nullable();
            $table->string('location', 50)->nullable();
            $table->integer('telegram_id')->nullable();
            $table->text('files')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['title', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('opportunities');
    }
}
