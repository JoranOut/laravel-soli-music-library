<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('piece_orchestra', 'speelperiodes');

        $table = config('permission.table_names.permissions', 'permissions');

        if (Schema::hasTable($table)) {
            DB::table($table)
                ->where('name', 'view gebruik')
                ->update(['name' => 'view speelperiode']);

            DB::table($table)
                ->where('name', 'create gebruik')
                ->update(['name' => 'create speelperiode']);

            DB::table($table)
                ->where('name', 'edit gebruik')
                ->update(['name' => 'edit speelperiode']);

            DB::table($table)
                ->where('name', 'delete gebruik')
                ->update(['name' => 'delete speelperiode']);
        }
    }

    public function down(): void
    {
        Schema::rename('speelperiodes', 'piece_orchestra');

        $table = config('permission.table_names.permissions', 'permissions');

        if (Schema::hasTable($table)) {
            DB::table($table)
                ->where('name', 'view speelperiode')
                ->update(['name' => 'view gebruik']);

            DB::table($table)
                ->where('name', 'create speelperiode')
                ->update(['name' => 'create gebruik']);

            DB::table($table)
                ->where('name', 'edit speelperiode')
                ->update(['name' => 'edit gebruik']);

            DB::table($table)
                ->where('name', 'delete speelperiode')
                ->update(['name' => 'delete gebruik']);
        }
    }
};
