<?php

namespace Database\Seeders;

use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use Illuminate\Database\Seeder;

class InstrumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // Bas
            ['name' => 'Bas', 'family' => 'Bas'],
            ['name' => 'BesBas', 'family' => 'Bas'],
            ['name' => 'Contrabas', 'family' => 'Bas'],
            ['name' => 'Esbas', 'family' => 'Bas'],
            ['name' => 'Sousafoon', 'family' => 'Bas'],

            // Directiepartijen
            ['name' => 'Dirigent', 'family' => 'Directiepartijen'],
            ['name' => 'Partituur', 'family' => 'Directiepartijen'],
            ['name' => 'Tamboer-maître', 'family' => 'Directiepartijen'],

            // Diverse
            ['name' => 'Harp', 'family' => 'Diverse'],
            ['name' => 'Majorette', 'family' => 'Diverse'],
            ['name' => 'Strijk', 'family' => 'Diverse'],
            ['name' => 'Vlaggenwacht', 'family' => 'Diverse'],

            // Dwarsfluit
            ['name' => 'Dwarsfluit', 'family' => 'Dwarsfluit'],
            ['name' => 'Piccolo', 'family' => 'Dwarsfluit'],

            // Fagot
            ['name' => 'Contrafagot', 'family' => 'Fagot'],
            ['name' => 'Fagot', 'family' => 'Fagot'],

            // Gitaar
            ['name' => 'Basgitaar', 'family' => 'Gitaar'],
            ['name' => 'Gitaar', 'family' => 'Gitaar'],

            // Hobo
            ['name' => 'Althobo', 'family' => 'Hobo'],
            ['name' => 'Hobo', 'family' => 'Hobo'],

            // Hoorn
            ['name' => 'Hoorn', 'family' => 'Hoorn'],

            // Klarinet
            ['name' => 'Altklarinet', 'family' => 'Klarinet'],
            ['name' => 'Basklarinet', 'family' => 'Klarinet'],
            ['name' => 'Besklarinet', 'family' => 'Klarinet'],
            ['name' => 'Contrabasklarinet', 'family' => 'Klarinet'],
            ['name' => 'Esklarinet', 'family' => 'Klarinet'],
            ['name' => 'Klarinet', 'family' => 'Klarinet'],

            // Klein koper
            ['name' => 'Bugel', 'family' => 'Klein koper'],
            ['name' => 'Cornet', 'family' => 'Klein koper'],
            ['name' => 'Trompet', 'family' => 'Klein koper'],

            // Saxofoon
            ['name' => 'Altsaxofoon', 'family' => 'Saxofoon'],
            ['name' => 'Baritonsaxofoon', 'family' => 'Saxofoon'],
            ['name' => 'Bassaxofoon', 'family' => 'Saxofoon'],
            ['name' => 'Contrabassaxofoon', 'family' => 'Saxofoon'],
            ['name' => 'Saxofoon', 'family' => 'Saxofoon'],
            ['name' => 'Sopraansaxofoon', 'family' => 'Saxofoon'],
            ['name' => 'Tenorsaxofoon', 'family' => 'Saxofoon'],

            // Slagwerk
            ['name' => 'Bekken', 'family' => 'Slagwerk'],
            ['name' => 'Buisklokken', 'family' => 'Slagwerk'],
            ['name' => 'Drumstel', 'family' => 'Slagwerk'],
            ['name' => 'Kleine trom', 'family' => 'Slagwerk'],
            ['name' => 'Klokkenspel', 'family' => 'Slagwerk'],
            ['name' => 'Marimba', 'family' => 'Slagwerk'],
            ['name' => 'Melodisch slagwerk', 'family' => 'Slagwerk'],
            ['name' => 'Paradetrom', 'family' => 'Slagwerk'],
            ['name' => 'Pauken', 'family' => 'Slagwerk'],
            ['name' => 'Percussion', 'family' => 'Slagwerk'],
            ['name' => 'Slagwerk', 'family' => 'Slagwerk'],
            ['name' => 'Trio tom', 'family' => 'Slagwerk'],
            ['name' => 'Trom', 'family' => 'Slagwerk'],
            ['name' => 'Vibrafoon', 'family' => 'Slagwerk'],
            ['name' => 'Xylofoon', 'family' => 'Slagwerk'],

            // Toetsen
            ['name' => 'Keyboard', 'family' => 'Toetsen'],
            ['name' => 'Orgel', 'family' => 'Toetsen'],
            ['name' => 'Piano', 'family' => 'Toetsen'],

            // Trombone
            ['name' => 'Bastrombone', 'family' => 'Trombone'],
            ['name' => 'Trombone', 'family' => 'Trombone'],

            // Tuba
            ['name' => 'Althoorn', 'family' => 'Tuba'],
            ['name' => 'Bariton', 'family' => 'Tuba'],
            ['name' => 'Euphonium', 'family' => 'Tuba'],
            ['name' => 'Tuba', 'family' => 'Tuba'],

            // Zang
            ['name' => 'Zang', 'family' => 'Zang'],
        ];

        // Create families first
        $familyNames = collect($types)->pluck('family')->unique();
        $familyMap = [];
        foreach ($familyNames as $name) {
            $family = InstrumentFamily::updateOrCreate(['name' => $name]);
            $familyMap[$name] = $family->id;
        }

        // Create types with FK
        foreach ($types as $type) {
            InstrumentType::updateOrCreate(
                ['name' => $type['name']],
                ['instrument_family_id' => $familyMap[$type['family']]]
            );
        }
    }
}
