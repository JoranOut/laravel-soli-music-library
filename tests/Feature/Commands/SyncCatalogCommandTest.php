<?php

use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Models\Part;
use App\Models\Piece;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->onderdelenResponse = [
        'onderdelen' => [
            ['id' => 1, 'naam' => 'Harmonie orkest', 'afkorting' => 'HO', 'type' => 'orkest', 'actief' => true],
            ['id' => 2, 'naam' => 'Bigband', 'afkorting' => 'BB', 'type' => 'ensemble', 'actief' => false],
        ],
    ];

    $this->instrumentsResponse = [
        'families' => [
            ['id' => 10, 'naam' => 'Houtblazers'],
            ['id' => 20, 'naam' => 'Koperblazers'],
        ],
        'soorten' => [
            ['id' => 100, 'naam' => 'Klarinet', 'instrument_familie_id' => 10],
            ['id' => 101, 'naam' => 'Dwarsfluit', 'instrument_familie_id' => 10],
            ['id' => 200, 'naam' => 'Trompet', 'instrument_familie_id' => 20],
        ],
    ];
});

it('syncs orchestras, families, and instrument types from admin API', function () {
    Http::fake([
        '*/api/v1/onderdelen' => Http::response($this->onderdelenResponse),
        '*/api/v1/instruments' => Http::response($this->instrumentsResponse),
    ]);

    $this->artisan('music:sync-catalog')
        ->expectsOutputToContain('Synced 2 orchestras, 2 families, 3 instrument types')
        ->assertSuccessful();

    expect(Orchestra::count())->toBe(2)
        ->and(InstrumentFamily::count())->toBe(2)
        ->and(InstrumentType::count())->toBe(3);

    $orchestra = Orchestra::where('external_id', 1)->first();
    expect($orchestra->name)->toBe('Harmonie orkest')
        ->and($orchestra->abbreviation)->toBe('HO')
        ->and($orchestra->type)->toBe('orkest')
        ->and($orchestra->is_active)->toBeTrue();

    $family = InstrumentFamily::where('external_id', 10)->first();
    expect($family->name)->toBe('Houtblazers');

    $type = InstrumentType::where('external_id', 100)->first();
    expect($type->name)->toBe('Klarinet')
        ->and($type->instrument_family_id)->toBe($family->id);
});

it('is idempotent when run twice', function () {
    Http::fake([
        '*/api/v1/onderdelen' => Http::response($this->onderdelenResponse),
        '*/api/v1/instruments' => Http::response($this->instrumentsResponse),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();
    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(Orchestra::count())->toBe(2)
        ->and(InstrumentFamily::count())->toBe(2)
        ->and(InstrumentType::count())->toBe(3);
});

it('updates existing records on re-sync', function () {
    Orchestra::create(['external_id' => 1, 'name' => 'Oud', 'abbreviation' => 'O', 'type' => 'orkest', 'is_active' => true]);

    Http::fake([
        '*/api/v1/onderdelen' => Http::response([
            'onderdelen' => [
                ['id' => 1, 'naam' => 'Harmonie orkest (nieuw)', 'afkorting' => 'HON', 'type' => 'orkest', 'actief' => false],
            ],
        ]),
        '*/api/v1/instruments' => Http::response(['families' => [], 'soorten' => []]),
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

it('maps external_id correctly for all models', function () {
    Http::fake([
        '*/api/v1/onderdelen' => Http::response($this->onderdelenResponse),
        '*/api/v1/instruments' => Http::response($this->instrumentsResponse),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(Orchestra::where('external_id', 1)->exists())->toBeTrue()
        ->and(Orchestra::where('external_id', 2)->exists())->toBeTrue()
        ->and(InstrumentFamily::where('external_id', 10)->exists())->toBeTrue()
        ->and(InstrumentFamily::where('external_id', 20)->exists())->toBeTrue()
        ->and(InstrumentType::where('external_id', 100)->exists())->toBeTrue()
        ->and(InstrumentType::where('external_id', 101)->exists())->toBeTrue()
        ->and(InstrumentType::where('external_id', 200)->exists())->toBeTrue();
});

it('soft-deletes removed instrument types and preserves parts', function () {
    $family = InstrumentFamily::factory()->create(['external_id' => 20]);
    $kept = InstrumentType::factory()->create(['external_id' => 200, 'instrument_family_id' => $family->id]);
    $removed = InstrumentType::factory()->create(['external_id' => 100, 'instrument_family_id' => $family->id]);
    $part = Part::factory()->create(['instrument_type_id' => $removed->id]);

    Http::fake([
        '*/api/v1/onderdelen' => Http::response(['onderdelen' => []]),
        '*/api/v1/instruments' => Http::response([
            'families' => [
                ['id' => 20, 'naam' => $family->name],
            ],
            'soorten' => [
                ['id' => 200, 'naam' => 'Trompet', 'instrument_familie_id' => 20],
            ],
        ]),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(InstrumentType::count())->toBe(1)
        ->and(InstrumentType::withTrashed()->count())->toBe(2)
        ->and(Part::find($part->id))->not->toBeNull();
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
        '*/api/v1/instruments' => Http::response(['families' => [], 'soorten' => []]),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(Orchestra::count())->toBe(1)
        ->and(Orchestra::withTrashed()->count())->toBe(2)
        ->and($piece->speelperiodes()->count())->toBe(1);
});

it('restores previously soft-deleted records when they reappear', function () {
    $orchestra = Orchestra::factory()->create(['external_id' => 1, 'name' => 'Harmonie orkest']);
    Orchestra::factory()->create(['external_id' => 2, 'name' => 'Bigband']);

    // Soft-delete both
    Orchestra::query()->delete();

    expect(Orchestra::count())->toBe(0)
        ->and(Orchestra::withTrashed()->count())->toBe(2);

    Http::fake([
        '*/api/v1/onderdelen' => Http::response([
            'onderdelen' => [
                ['id' => 1, 'naam' => 'Harmonie orkest', 'afkorting' => 'HO', 'type' => 'orkest', 'actief' => true],
            ],
        ]),
        '*/api/v1/instruments' => Http::response(['families' => [], 'soorten' => []]),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(Orchestra::count())->toBe(1)
        ->and(Orchestra::withTrashed()->count())->toBe(2);

    $restored = Orchestra::where('external_id', 1)->first();
    expect($restored->deleted_at)->toBeNull();
});

it('skips instrument types with unknown family', function () {
    Http::fake([
        '*/api/v1/onderdelen' => Http::response(['onderdelen' => []]),
        '*/api/v1/instruments' => Http::response([
            'families' => [
                ['id' => 10, 'naam' => 'Houtblazers'],
            ],
            'soorten' => [
                ['id' => 100, 'naam' => 'Klarinet', 'instrument_familie_id' => 10],
                ['id' => 999, 'naam' => 'Onbekend', 'instrument_familie_id' => 99],
            ],
        ]),
    ]);

    $this->artisan('music:sync-catalog')->assertSuccessful();

    expect(InstrumentType::count())->toBe(1)
        ->and(InstrumentType::where('external_id', 100)->exists())->toBeTrue()
        ->and(InstrumentType::where('external_id', 999)->exists())->toBeFalse();
});
