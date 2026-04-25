<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->string('bought_for')->nullable();
            $table->date('buy_date')->nullable();
            $table->json('genre')->nullable();
            $table->string('music_type')->nullable();
            $table->string('archive_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->dropColumn(['bought_for', 'buy_date', 'genre', 'music_type', 'archive_number']);
        });
    }
};
