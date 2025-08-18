<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (Schema::hasColumn('files', 'width')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE files ALTER COLUMN width TYPE INTEGER USING width::integer');
            }
            DB::statement('ALTER TABLE files ALTER COLUMN width DROP NOT NULL');
        }

        if (Schema::hasColumn('files', 'height')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE files ALTER COLUMN height TYPE INTEGER USING height::integer');
            }
            DB::statement('ALTER TABLE files ALTER COLUMN height DROP NOT NULL');
        }

        if (Schema::hasColumn('files', 'size')) {
            if(DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE files ALTER COLUMN size TYPE INTEGER USING size::integer');
            }
        }
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
