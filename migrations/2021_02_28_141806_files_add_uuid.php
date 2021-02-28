<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FilesAddUuid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('files', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');

            $table->index('uuid');
        });

        $items = DB::select('SELECT id FROM files');
        foreach ($items as $item) {
            DB::update('UPDATE files SET uuid = "' . \Ramsey\Uuid\Uuid::uuid4() . '" WHERE id=' . $item->id);
        }

        Schema::table('files', function (Blueprint $table) {
            $table->uuid('uuid')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
}
