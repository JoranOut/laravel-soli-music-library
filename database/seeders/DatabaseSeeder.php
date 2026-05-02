<?php

namespace Database\Seeders;

use App\Models\DownloadLog;
use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Models\Part;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Sync orchestras and instruments from admin, fall back to hardcoded data
        $this->seedCatalog();

        // Fixed seed for deterministic output
        fake()->seed(42);

        $partituurPdf = file_get_contents(database_path('seeders/fixtures/partituur.pdf'));
        $partPdfs = [
            file_get_contents(database_path('seeders/fixtures/ode-to-joy-alt-sax.pdf')),
            file_get_contents(database_path('seeders/fixtures/ode-to-joy-flute.pdf')),
            file_get_contents(database_path('seeders/fixtures/ode-to-joy-klarinet.pdf')),
            file_get_contents(database_path('seeders/fixtures/ode-to-joy-tenor-sax.pdf')),
        ];

        // Users
        $users = collect();
        $users->push(User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'oidc_roles' => ['member', 'muziekbeheer'],
        ]));
        $users->push(User::factory()->create(['oidc_roles' => ['member']]));
        $users->push(User::factory()->create(['oidc_roles' => ['member', 'contributor']]));

        $orchestras = Orchestra::orderBy('sort_order')->get();
        $instrumentTypes = InstrumentType::all();

        // Pieces with orchestra assignments and parts (real PDFs on disk)
        $piecesData = [
            ['title' => 'Bohemian Rhapsody', 'composer' => 'Freddie Mercury', 'arranger' => 'John Higgins', 'difficulty' => 'hard', 'genre' => ['Pop'], 'music_type' => 'Concert', 'bought_for' => 'Harmonie orkest', 'archive_number' => 'A-001'],
            ['title' => 'Mars van de Medici', 'composer' => 'Johan Wichers', 'difficulty' => 'medium', 'genre' => ['Wals'], 'music_type' => 'Loopmars', 'archive_number' => 'A-002'],
            ['title' => 'Highlights from Frozen', 'composer' => 'Robert Lopez', 'arranger' => 'Sean O\'Loughlin', 'difficulty' => 'medium', 'genre' => ['Filmmuziek', 'Musical'], 'music_type' => 'Concert', 'buy_date' => '2024-03-15'],
            ['title' => 'The Final Countdown', 'composer' => 'Joey Tempest', 'arranger' => 'Michael Brown', 'difficulty' => 'easy', 'genre' => ['Pop'], 'music_type' => 'Concert'],
            ['title' => 'Slippery When Wet', 'composer' => 'Kees Vlak', 'difficulty' => 'medium', 'genre' => ['Overture'], 'music_type' => 'Concourswerk'],
            ['title' => 'African Symphony', 'composer' => 'Van McCoy', 'arranger' => 'Naohiro Iwai', 'difficulty' => 'hard', 'genre' => ['Klassiek'], 'music_type' => 'Concert', 'bought_for' => 'Klein Orkest'],
            ['title' => 'A Moorside Suite', 'composer' => 'Gustav Holst', 'difficulty' => 'very hard', 'genre' => ['Klassiek'], 'music_type' => 'Concourswerk', 'archive_number' => 'A-007'],
            ['title' => 'Riverdance', 'composer' => 'Bill Whelan', 'arranger' => 'Jeff Cranfill', 'difficulty' => 'medium', 'genre' => ['Filmmuziek'], 'music_type' => 'Divers'],
        ];

        foreach ($piecesData as $pieceData) {
            $piece = Piece::factory()->create($pieceData);

            // Assign to 1-2 random orchestras with usage records
            $randomOrchestras = $orchestras->random(fake()->numberBetween(1, 2));
            foreach ($randomOrchestras as $orchestra) {
                $piece->orchestraUsages()->create([
                    'orchestra_id' => $orchestra->id,
                    'van' => fake()->optional(0.7)->dateTimeBetween('-5 years', 'now')?->format('Y-m-d'),
                ]);
            }

            // Create conductor part with the real Ode to Joy PDF
            $conductorType = $instrumentTypes->random();
            $conductorPath = "pieces/{$piece->id}/partituur.pdf";
            Storage::disk('sheets')->put($conductorPath, $partituurPdf);

            Part::factory()->partituur()->create([
                'piece_id' => $piece->id,
                'instrument_type_id' => $conductorType->id,
                'file_path' => $conductorPath,
                'original_filename' => 'Partituur.pdf',
            ]);

            // Create 3-6 instrument parts with real PDFs
            $selectedTypes = $instrumentTypes->random(fake()->numberBetween(3, 6));
            foreach ($selectedTypes as $index => $type) {
                $voice = fake()->optional(0.5)->numberBetween(1, 3);
                $partPath = "pieces/{$piece->id}/{$type->name}.pdf";
                Storage::disk('sheets')->put($partPath, $partPdfs[$index % count($partPdfs)]);

                Part::factory()->create([
                    'piece_id' => $piece->id,
                    'instrument_type_id' => $type->id,
                    'voice' => $voice,
                    'file_path' => $partPath,
                    'original_filename' => $type->name.'.pdf',
                ]);
            }
        }

        // Download logs
        $allParts = Part::all();
        foreach ($users as $user) {
            $downloadCount = fake()->numberBetween(2, 8);
            for ($i = 0; $i < $downloadCount; $i++) {
                DownloadLog::create([
                    'user_id' => $user->id,
                    'part_id' => $allParts->random()->id,
                    'downloaded_at' => fake()->dateTimeBetween('-3 months'),
                    'ip' => fake()->ipv4(),
                ]);
            }
        }
    }

    private function seedCatalog(): void
    {
        try {
            Artisan::call('music:sync-catalog');
            $this->command->info(Artisan::output());
        } catch (\Throwable $e) {
            Log::warning('Catalog sync failed during seeding, using fallback data: '.$e->getMessage());
            $this->command->warn('Catalog sync failed, using fallback data.');
            $this->seedFallbackCatalog();
        }
    }

    private function seedFallbackCatalog(): void
    {
        $orchestrasData = [
            ['name' => 'Harmonie orkest', 'abbreviation' => 'HO', 'type' => 'orkest', 'sort_order' => 1],
            ['name' => 'Klein Orkest', 'abbreviation' => 'KO', 'type' => 'orkest', 'sort_order' => 2],
            ['name' => 'Bigband', 'abbreviation' => 'BB', 'type' => 'ensemble', 'sort_order' => 3],
            ['name' => 'Slagwerkgroep', 'abbreviation' => 'SWG', 'type' => 'groep', 'sort_order' => 4],
        ];

        foreach ($orchestrasData as $i => $data) {
            Orchestra::factory()->create([...$data, 'external_id' => $i + 1]);
        }

        $familiesData = [
            'Koper' => ['Trompet', 'Cornet', 'Bugel', 'Hoorn', 'Trombone', 'Euphonium', 'Tuba'],
            'Hout' => ['Klarinet', 'Klarinet Bes', 'Basklarinet', 'Hobo', 'Fagot'],
            'Saxofoon' => ['Sopraansax', 'Altsax', 'Tenorsax', 'Baritonsax'],
            'Dwarsfluit' => ['Dwarsfluit', 'Piccolo'],
            'Slagwerk' => ['Pauken', 'Mallet', 'Drumstel', 'Percussie'],
            'Partituur' => ['Partituur'],
        ];

        $externalId = 1;
        $familyExternalId = 1;
        foreach ($familiesData as $familyName => $types) {
            $family = InstrumentFamily::factory()->create([
                'external_id' => $familyExternalId++,
                'name' => $familyName,
            ]);

            foreach ($types as $sortOrder => $typeName) {
                InstrumentType::factory()->create([
                    'external_id' => $externalId++,
                    'name' => $typeName,
                    'instrument_family_id' => $family->id,
                    'sort_order' => $sortOrder,
                ]);
            }
        }
    }
}
