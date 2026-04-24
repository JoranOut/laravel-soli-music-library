<?php

namespace App\Console\Commands;

use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Services\AdminApiService;
use Illuminate\Console\Command;
use Throwable;

class SyncCatalogCommand extends Command
{
    protected $signature = 'music:sync-catalog';

    protected $description = 'Sync orchestras, instrument families, and instrument types from admin';

    public function handle(AdminApiService $api): int
    {
        try {
            $orchestraCount = $this->syncOrchestras($api);
            [$familyCount, $typeCount] = $this->syncInstruments($api);
        } catch (Throwable $e) {
            $this->error("Catalog sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Synced {$orchestraCount} orchestras, {$familyCount} families, {$typeCount} instrument types");

        return self::SUCCESS;
    }

    private function syncOrchestras(AdminApiService $api): int
    {
        $data = $api->getOnderdelen();

        $externalIds = [];

        foreach ($data['onderdelen'] as $onderdeel) {
            $orchestra = Orchestra::withTrashed()->updateOrCreate(
                ['external_id' => $onderdeel['id']],
                [
                    'name' => $onderdeel['naam'],
                    'abbreviation' => $onderdeel['afkorting'],
                    'type' => $onderdeel['type'],
                    'is_active' => $onderdeel['actief'],
                ],
            );
            $orchestra->restore();
            $externalIds[] = $onderdeel['id'];
        }

        Orchestra::whereNotIn('external_id', $externalIds)->delete();

        return count($data['onderdelen']);
    }

    /**
     * @return array{int, int}
     */
    private function syncInstruments(AdminApiService $api): array
    {
        $data = $api->getInstruments();

        $familyExternalIds = [];

        foreach ($data['families'] as $family) {
            $instrumentFamily = InstrumentFamily::withTrashed()->updateOrCreate(
                ['external_id' => $family['id']],
                ['name' => $family['naam']],
            );
            $instrumentFamily->restore();
            $familyExternalIds[] = $family['id'];
        }

        $typeExternalIds = [];

        foreach ($data['soorten'] as $soort) {
            $family = InstrumentFamily::where('external_id', $soort['instrument_familie_id'])->first();

            if (! $family) {
                continue;
            }

            $instrumentType = InstrumentType::withTrashed()->updateOrCreate(
                ['external_id' => $soort['id']],
                [
                    'name' => $soort['naam'],
                    'instrument_family_id' => $family->id,
                ],
            );
            $instrumentType->restore();
            $typeExternalIds[] = $soort['id'];
        }

        InstrumentType::whereNotIn('external_id', $typeExternalIds)->delete();
        InstrumentFamily::whereNotIn('external_id', $familyExternalIds)->delete();

        return [count($data['families']), count($data['soorten'])];
    }
}
