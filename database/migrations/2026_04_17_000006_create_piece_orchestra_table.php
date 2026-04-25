<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piece_orchestra', function (Blueprint $table) {
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->foreignId('orchestra_id')->constrained()->cascadeOnDelete();
            $table->primary(['piece_id', 'orchestra_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_orchestra');
    }
};
