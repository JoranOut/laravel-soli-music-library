<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instrument_types', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('instrument_families', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('orchestras', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('download_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->change()->nullable()->constrained()->nullOnDelete();

            $table->dropForeign(['part_id']);
            $table->foreignId('part_id')->change()->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('instrument_types', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('instrument_families', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('orchestras', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('download_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->change()->nullable(false)->constrained()->cascadeOnDelete();

            $table->dropForeign(['part_id']);
            $table->foreignId('part_id')->change()->nullable(false)->constrained()->cascadeOnDelete();
        });
    }
};
