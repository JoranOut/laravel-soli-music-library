<?php

use App\Models\Orchestra;
use App\Models\Part;
use App\Models\Piece;
use App\Models\Speelperiode;

it('can be created with factory', function () {
    $piece = Piece::factory()->create();

    expect($piece)->toBeInstanceOf(Piece::class)
        ->and($piece->title)->toBeString();
});

it('allows nullable fields', function () {
    $piece = Piece::factory()->create([
        'composer' => null,
        'arranger' => null,
        'publisher' => null,
        'difficulty' => null,
        'notes' => null,
    ]);

    $piece->refresh();

    expect($piece->composer)->toBeNull()
        ->and($piece->arranger)->toBeNull()
        ->and($piece->publisher)->toBeNull()
        ->and($piece->difficulty)->toBeNull()
        ->and($piece->notes)->toBeNull();
});

it('belongs to many orchestras via usages', function () {
    $piece = Piece::factory()->create();
    $orchestras = Orchestra::factory(2)->create();

    foreach ($orchestras as $orchestra) {
        $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra->id]);
    }

    expect($piece->orchestras)->toHaveCount(2)
        ->each->toBeInstanceOf(Orchestra::class);
});

// --- Speelperiode active/inactive: all van/tot combinations ---

// van=null: no explicit start date, treated as always started
it('shows speelperiode with van=null, tot=null', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => null, 'tot' => null, 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->toContain($orchestra->id);
});

it('hides speelperiode with van=null, tot=past', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => null, 'tot' => now()->subDay()->toDateString(), 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->not->toContain($orchestra->id);
});

it('shows speelperiode with van=null, tot=today', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => null, 'tot' => now()->toDateString(), 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->toContain($orchestra->id);
});

it('shows speelperiode with van=null, tot=future', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => null, 'tot' => now()->addMonth()->toDateString(), 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->toContain($orchestra->id);
});

// van=past
it('shows speelperiode with van=past, tot=null', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => now()->subMonth()->toDateString(), 'tot' => null, 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->toContain($orchestra->id);
});

it('hides speelperiode with van=past, tot=past', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => now()->subMonth()->toDateString(), 'tot' => now()->subDay()->toDateString(), 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->not->toContain($orchestra->id);
});

it('shows speelperiode with van=past, tot=today', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => now()->subMonth()->toDateString(), 'tot' => now()->toDateString(), 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->toContain($orchestra->id);
});

it('shows speelperiode with van=past, tot=future', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => now()->subMonth()->toDateString(), 'tot' => now()->addMonth()->toDateString(), 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->toContain($orchestra->id);
});

// van=today
it('shows speelperiode with van=today, tot=null', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => now()->toDateString(), 'tot' => null, 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->toContain($orchestra->id);
});

it('shows speelperiode with van=today, tot=today', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => now()->toDateString(), 'tot' => now()->toDateString(), 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->toContain($orchestra->id);
});

it('shows speelperiode with van=today, tot=future', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => now()->toDateString(), 'tot' => now()->addMonth()->toDateString(), 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->toContain($orchestra->id);
});

// van=future: never active yet
it('hides speelperiode with van=future, tot=null', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => now()->addMonth()->toDateString(), 'tot' => null, 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->not->toContain($orchestra->id);
});

it('hides speelperiode with van=future, tot=future', function () {
    $piece = Piece::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece->speelperiodes()->create(['van' => now()->addMonth()->toDateString(), 'tot' => now()->addMonths(2)->toDateString(), 'orchestra_id' => $orchestra->id]);

    expect($piece->orchestras->pluck('id')->toArray())->not->toContain($orchestra->id);
});

it('has many speelperiodes', function () {
    $piece = Piece::factory()->create();
    $orchestras = Orchestra::factory(3)->create();

    foreach ($orchestras as $orchestra) {
        $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra->id]);
    }

    expect($piece->speelperiodes)->toHaveCount(3)
        ->each->toBeInstanceOf(Speelperiode::class);
});

it('has many parts', function () {
    $piece = Piece::factory()->create();
    Part::factory(4)->create(['piece_id' => $piece->id]);

    expect($piece->parts)->toHaveCount(4)
        ->each->toBeInstanceOf(Part::class);
});

it('can be soft-deleted', function () {
    $piece = Piece::factory()->create();

    $piece->delete();

    expect(Piece::find($piece->id))->toBeNull();
    expect(Piece::withTrashed()->find($piece->id))->not->toBeNull();
    expect($piece->fresh()->deleted_at)->not->toBeNull();
});

it('preserves parts when archived', function () {
    $piece = Piece::factory()->create();
    Part::factory(3)->create(['piece_id' => $piece->id]);

    $piece->delete();

    expect(Part::where('piece_id', $piece->id)->count())->toBe(3);
});

it('is excluded from normal queries when archived', function () {
    Piece::factory()->create();
    $archived = Piece::factory()->create();
    $archived->delete();

    expect(Piece::count())->toBe(1);
    expect(Piece::withTrashed()->count())->toBe(2);
});
