<?php

use App\Models\InstrumentFamily;
use App\Models\InstrumentType;

// ---------------------------------------------------------------------------
// Authentication — API key required
// ---------------------------------------------------------------------------

it('rejects requests without API key', function () {
    $this->getJson('/api/v1/instruments')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Invalid API key.']);
});

it('rejects requests with invalid API key', function () {
    config(['services.soli_instruments_api.api_key' => 'valid-key']);

    $this->getJson('/api/v1/instruments', ['X-API-Key' => 'wrong-key'])
        ->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Authenticated access — returns instrument data
// ---------------------------------------------------------------------------

it('returns families, soorten and replacements with valid API key', function () {
    config(['services.soli_instruments_api.api_key' => 'test-key']);

    $family = InstrumentFamily::factory()->create(['name' => 'Brass']);
    InstrumentType::factory()->create([
        'name' => 'Trumpet',
        'instrument_family_id' => $family->id,
    ]);
    InstrumentType::factory()->create([
        'name' => 'Trombone',
        'instrument_family_id' => $family->id,
    ]);

    $response = $this->getJson('/api/v1/instruments', ['X-API-Key' => 'test-key'])
        ->assertOk()
        ->assertJsonStructure([
            'families' => [['id', 'name']],
            'soorten' => [['id', 'name', 'instrument_family_id']],
            'replacements',
        ]);

    $data = $response->json();
    expect($data['families'])->toHaveCount(1)
        ->and($data['families'][0]['name'])->toBe('Brass')
        ->and($data['soorten'])->toHaveCount(2)
        ->and($data['replacements'])->toBeEmpty();
});

it('returns empty arrays when no data exists', function () {
    config(['services.soli_instruments_api.api_key' => 'test-key']);

    $this->getJson('/api/v1/instruments', ['X-API-Key' => 'test-key'])
        ->assertOk()
        ->assertJson(['families' => [], 'soorten' => [], 'replacements' => []]);
});

it('excludes soft-deleted instrument types from soorten', function () {
    config(['services.soli_instruments_api.api_key' => 'test-key']);

    $family = InstrumentFamily::factory()->create();
    $active = InstrumentType::factory()->create(['name' => 'Active', 'instrument_family_id' => $family->id]);
    $deleted = InstrumentType::factory()->create(['name' => 'Deleted', 'instrument_family_id' => $family->id]);
    $deleted->delete();

    $response = $this->getJson('/api/v1/instruments', ['X-API-Key' => 'test-key'])
        ->assertOk();

    $names = collect($response->json('soorten'))->pluck('name')->all();
    expect($names)->toContain('Active')
        ->and($names)->not->toContain('Deleted');
});

it('includes replacements for deleted types with replaced_by_id', function () {
    config(['services.soli_instruments_api.api_key' => 'test-key']);

    $family = InstrumentFamily::factory()->create();
    $replacement = InstrumentType::factory()->create(['name' => 'Klarinet in Bes', 'instrument_family_id' => $family->id]);
    $deleted = InstrumentType::factory()->create([
        'name' => 'Klarinet',
        'instrument_family_id' => $family->id,
        'replaced_by_id' => $replacement->id,
    ]);
    $deleted->delete();

    $response = $this->getJson('/api/v1/instruments', ['X-API-Key' => 'test-key'])
        ->assertOk();

    $replacements = $response->json('replacements');
    expect($replacements)->toHaveCount(1)
        ->and($replacements[0]['old_name'])->toBe('Klarinet')
        ->and($replacements[0]['new_name'])->toBe('Klarinet in Bes');
});

it('excludes deleted types without replaced_by_id from replacements', function () {
    config(['services.soli_instruments_api.api_key' => 'test-key']);

    $family = InstrumentFamily::factory()->create();
    $deleted = InstrumentType::factory()->create(['name' => 'Obsolete', 'instrument_family_id' => $family->id]);
    $deleted->delete();

    $response = $this->getJson('/api/v1/instruments', ['X-API-Key' => 'test-key'])
        ->assertOk();

    expect($response->json('replacements'))->toBeEmpty();
});

it('excludes soft-deleted instrument families', function () {
    config(['services.soli_instruments_api.api_key' => 'test-key']);

    $active = InstrumentFamily::factory()->create(['name' => 'Active Family']);
    $deleted = InstrumentFamily::factory()->create(['name' => 'Deleted Family']);
    $deleted->delete();

    $response = $this->getJson('/api/v1/instruments', ['X-API-Key' => 'test-key'])
        ->assertOk();

    $names = collect($response->json('families'))->pluck('name')->all();
    expect($names)->toContain('Active Family')
        ->and($names)->not->toContain('Deleted Family');
});

it('returns families ordered by name', function () {
    config(['services.soli_instruments_api.api_key' => 'test-key']);

    InstrumentFamily::factory()->create(['name' => 'Zang']);
    InstrumentFamily::factory()->create(['name' => 'Bas']);
    InstrumentFamily::factory()->create(['name' => 'Koper']);

    $response = $this->getJson('/api/v1/instruments', ['X-API-Key' => 'test-key'])
        ->assertOk();

    $names = collect($response->json('families'))->pluck('name')->all();
    expect($names)->toBe(['Bas', 'Koper', 'Zang']);
});

it('returns soorten ordered by name', function () {
    config(['services.soli_instruments_api.api_key' => 'test-key']);

    $family = InstrumentFamily::factory()->create();
    InstrumentType::factory()->create(['name' => 'Tuba', 'instrument_family_id' => $family->id]);
    InstrumentType::factory()->create(['name' => 'Alt', 'instrument_family_id' => $family->id]);
    InstrumentType::factory()->create(['name' => 'Klarinet', 'instrument_family_id' => $family->id]);

    $response = $this->getJson('/api/v1/instruments', ['X-API-Key' => 'test-key'])
        ->assertOk();

    $names = collect($response->json('soorten'))->pluck('name')->all();
    expect($names)->toBe(['Alt', 'Klarinet', 'Tuba']);
});
