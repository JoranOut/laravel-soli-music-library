<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AdminApiService
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.soli_admin_api.base_url'), '/');
        $this->apiKey = config('services.soli_admin_api.api_key') ?? '';
    }

    /**
     * @return array{families: array<int, array{id: int, naam: string}>, soorten: array<int, array{id: int, naam: string, instrument_familie_id: int}>}
     *
     * @throws ConnectionException
     */
    public function getInstruments(): array
    {
        return $this->get('/api/v1/instruments');
    }

    /**
     * @return array{onderdelen: array<int, array{id: int, naam: string, afkorting: string|null, type: string, actief: bool}>}
     *
     * @throws ConnectionException
     */
    public function getOnderdelen(): array
    {
        return $this->get('/api/v1/onderdelen');
    }

    /**
     * @throws ConnectionException
     */
    private function get(string $path): array
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->get($this->baseUrl.$path);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Admin API request to {$path} failed with status {$response->status()}: {$response->body()}"
            );
        }

        return $response->json();
    }
}
