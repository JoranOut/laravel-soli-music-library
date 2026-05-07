<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instrument_types', function (Blueprint $table) {
            $table->unsignedBigInteger('external_id')->nullable()->change();
        });

        Schema::table('instrument_families', function (Blueprint $table) {
            $table->unsignedBigInteger('external_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('instrument_types', function (Blueprint $table) {
            $table->unsignedBigInteger('external_id')->nullable(false)->change();
        });

        Schema::table('instrument_families', function (Blueprint $table) {
            $table->unsignedBigInteger('external_id')->nullable(false)->change();
        });
    }
};
