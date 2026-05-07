<?php

namespace App\Console\Commands;

use App\Models\Orchestra;
use App\Services\AdminApiService;
use Illuminate\Console\Command;
use Throwable;

class SyncCatalogCommand extends Command
{
    protected $signature = 'music:sync-catalog';

    protected $description = 'Sync orchestras from admin';

    public function handle(AdminApiService $api): int
    {
        try {
            $orchestraCount = $this->syncOrchestras($api);
        } catch (Throwable $e) {
            $this->error("Catalog sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Synced {$orchestraCount} orchestras");

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
}
