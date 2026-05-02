<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $musicTypes = [
            'Loopmars',
            'Tamboermars',
            'Concert',
            'Slagwerk',
            'Inspeelwerk',
            'Concourswerk',
            'Opleidings muziek',
            'Divers',
        ];

        $now = now();
        $rows = [];
        foreach ($musicTypes as $i => $name) {
            $rows[] = [
                'name' => $name,
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('music_types')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('music_types');
    }
};
