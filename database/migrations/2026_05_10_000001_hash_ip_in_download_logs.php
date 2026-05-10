<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('download_logs', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->change();
            $table->string('ip_hash', 64)->nullable()->after('ip');
            $table->string('country', 2)->nullable()->after('ip_hash');
        });
    }

    public function down(): void
    {
        Schema::table('download_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_hash', 'country']);
        });
    }
};
