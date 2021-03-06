<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Files extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('files') === false) {
            Schema::create('files', function (Blueprint $table) {
                $table->id();
                $table->string('scenario');
                $table->string('hash');
                $table->string('name');
                $table->string('ext');
                $table->string('mime');
                $table->string('size');
                $table->string('width')->nullable();
                $table->string('height')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('files');
    }
}
