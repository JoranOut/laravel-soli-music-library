<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->string('audio_youtube_url', 500)->nullable()->after('archive_number');
            $table->string('audio_file_path')->nullable()->after('audio_youtube_url');
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->dropColumn(['audio_youtube_url', 'audio_file_path']);
        });
    }
};
