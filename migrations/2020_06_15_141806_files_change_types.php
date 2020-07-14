<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FilesChangeTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'width')) {
                $table->unsignedInteger('width')->nullable()->change();
            }
            if (Schema::hasColumn('files', 'height')) {
                $table->unsignedInteger('height')->nullable()->change();
            }
            if (Schema::hasColumn('files', 'size')) {
                $table->unsignedInteger('size')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
