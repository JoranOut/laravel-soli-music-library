<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $genres = [
            'Ballet',
            'Dans',
            'Divers',
            'Fantasie',
            'Filmmuziek',
            'Gospel',
            'Hymne',
            'Karakterstuk',
            'Kerst',
            'Klassiek',
            'Jazz',
            'Medley',
            'Modern',
            'Musical',
            'Muziek met een Verhaal',
            'Opera',
            'Operette',
            'Overture',
            'Pop',
            'Ragtime',
            'Rhapsody',
            'Sint-Nicolaas',
            'Suite',
            'Symphony',
            'T-V Serie',
            'Volksmuziek',
            'Wals',
            'ZuidAmerikaans-Latin',
        ];

        $now = now();
        $rows = [];
        foreach ($genres as $i => $name) {
            $rows[] = [
                'name' => $name,
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('genres')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
