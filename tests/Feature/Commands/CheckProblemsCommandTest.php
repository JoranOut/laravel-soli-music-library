<?php

use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Models\Part;
use App\Models\Piece;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('sheets');
});

it('passes when there are no problems', function () {
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $this->artisan('music:check-problems')
        ->expectsOutputToContain('No problems found')
        ->assertSuccessful();
});

it('detects parts with soft-deleted instrument type', function () {
    $type = InstrumentType::factory()->create(['name' => 'Klarinet']);
    $piece = Piece::factory()->create(['title' => 'Test Stuk']);
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $type->id,
    ]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $type->delete();

    $this->artisan('music:check-problems')
        ->expectsOutputToContain('Parts with deleted instrument type')
        ->expectsOutputToContain('Klarinet')
        ->assertFailed();
});

it('detects piece-orchestra links with soft-deleted orchestra', function () {
    $orchestra = Orchestra::factory()->create(['name' => 'Bigband']);
    $piece = Piece::factory()->create(['title' => 'Test Stuk']);
    $piece->speelperiodes()->create(['orchestra_id' => $orchestra->id]);

    $orchestra->delete();

    $this->artisan('music:check-problems')
        ->expectsOutputToContain('Speelperiodes with deleted orchestra')
        ->expectsOutputToContain('Bigband')
        ->assertFailed();
});

it('detects parts with missing files', function () {
    Part::factory()->create(['file_path' => 'parts/nonexistent.pdf']);

    $this->artisan('music:check-problems')
        ->expectsOutputToContain('Parts with missing files')
        ->expectsOutputToContain('parts/nonexistent.pdf')
        ->assertFailed();
});

it('detects pieces without parts', function () {
    Piece::factory()->create(['title' => 'Leeg Stuk']);

    $this->artisan('music:check-problems')
        ->expectsOutputToContain('Pieces without parts')
        ->expectsOutputToContain('Leeg Stuk')
        ->assertFailed();
});

it('reports total problem count across categories', function () {
    // 1 piece without parts
    Piece::factory()->create();

    // 1 part with missing file
    $part1 = Part::factory()->create(['file_path' => 'parts/missing.pdf']);

    // 1 part with deleted instrument type + missing file
    $type = InstrumentType::factory()->create();
    $part2 = Part::factory()->create(['instrument_type_id' => $type->id, 'file_path' => 'parts/also-missing.pdf']);
    $type->delete();

    // Total: 1 (deleted type) + 2 (missing files) + 1 (piece without parts) = 4
    $this->artisan('music:check-problems')
        ->expectsOutputToContain('4 problem(s)')
        ->assertFailed();
});
