<?php

use App\Models\Orchestra;
use App\Models\Piece;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->onderdelenResponse = [
        'onderdelen' => [
            ['id' => 1, 'naam' => 'Harmonie orkest', 'afkorting' => 'HO', 'type' => 'orkest', 'actief' => true],
            ['id' => 2, 'naam' => 'Bigband', 'afkorting' => 'BB', 'type' => 'ensemble', 'actief' => false],
        ],
    ];
});

it('syncs orchestras from admin API', function () {
    Http::fake([
        '*/api/v1/onderdelen' => Http::response($this->onderdelenResponse),
    ]);

    $this->artisan('music:sync-catalog')
        ->expectsOutputToContain('Synced 2 orchestras')
        ->assertSuccessful();

    expect(Orchestra::count())->toBe(2);

    $orchestra = Orchestra::where('external_id', 1)->first();
    expect($orchestra->name)->toBe('Harmonie orkest')
        ->and($orchestra->abbreviation)->toBe('HO')
        ->and($orchestra->type)->toBe('orkest')
        ->and($orchestra->is_active)->toBeTrue();
});

it('is idempotent when run twice', function () {
    Http::fake([
        '*/api/v1/onderdelen' => Http::response($this->onderdelenResponse),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();
    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(Orchestra::count())->toBe(2);
});

it('updates existing records on re-sync', function () {
    Orchestra::create(['external_id' => 1, 'name' => 'Oud', 'abbreviation' => 'O', 'type' => 'orkest', 'is_active' => true]);

    Http::fake([
        '*/api/v1/onderdelen' => Http::response([
            'onderdelen' => [
                ['id' => 1, 'naam' => 'Harmonie orkest (nieuw)', 'afkorting' => 'HON', 'type' => 'orkest', 'actief' => false],
            ],
        ]),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(Orchestra::count())->toBe(1);

    $orchestra = Orchestra::where('external_id', 1)->first();
    expect($orchestra->name)->toBe('Harmonie orkest (nieuw)')
        ->and($orchestra->abbreviation)->toBe('HON')
        ->and($orchestra->is_active)->toBeFalse();
});

it('handles API failure gracefully', function () {
    Http::fake([
        '*/api/v1/onderdelen' => Http::response('Internal Server Error', 500),
    ]);

    $this->artisan('music:sync-catalog')
        ->expectsOutputToContain('Catalog sync failed')
        ->assertFailed();

    expect(Orchestra::count())->toBe(0);
});

it('maps external_id correctly for orchestras', function () {
    Http::fake([
        '*/api/v1/onderdelen' => Http::response($this->onderdelenResponse),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(Orchestra::where('external_id', 1)->exists())->toBeTrue()
        ->and(Orchestra::where('external_id', 2)->exists())->toBeTrue();
});

it('soft-deletes removed orchestras and preserves piece links', function () {
    Orchestra::factory()->create(['external_id' => 1, 'name' => 'Harmonie orkest']);
    Orchestra::factory()->create(['external_id' => 2, 'name' => 'Bigband']);
    $orchestra = Orchestra::where('external_id', 1)->first();
    $piece = Piece::factory()->create();
    $orchestra->speelperiodes()->create(['van' => now(), 'piece_id' => $piece->id]);

    Http::fake([
        '*/api/v1/onderdelen' => Http::response([
            'onderdelen' => [
                ['id' => 2, 'naam' => 'Bigband', 'afkorting' => 'BB', 'type' => 'ensemble', 'actief' => false],
            ],
        ]),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(Orchestra::count())->toBe(1)
        ->and(Orchestra::withTrashed()->count())->toBe(2)
        ->and($piece->speelperiodes()->count())->toBe(1);
});

it('restores previously soft-deleted records when they reappear', function () {
    Orchestra::factory()->create(['external_id' => 1, 'name' => 'Harmonie orkest']);
    Orchestra::factory()->create(['external_id' => 2, 'name' => 'Bigband']);

    Orchestra::query()->delete();

    expect(Orchestra::count())->toBe(0)
        ->and(Orchestra::withTrashed()->count())->toBe(2);

    Http::fake([
        '*/api/v1/onderdelen' => Http::response([
            'onderdelen' => [
                ['id' => 1, 'naam' => 'Harmonie orkest', 'afkorting' => 'HO', 'type' => 'orkest', 'actief' => true],
            ],
        ]),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(Orchestra::count())->toBe(1)
        ->and(Orchestra::withTrashed()->count())->toBe(2);

    $restored = Orchestra::where('external_id', 1)->first();
    expect($restored->deleted_at)->toBeNull();
});
