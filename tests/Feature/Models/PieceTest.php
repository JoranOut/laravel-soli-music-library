<?php

use App\Models\Orchestra;
use App\Models\Part;
use App\Models\Piece;
use App\Models\PieceOrchestra;

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
        $piece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);
    }

    expect($piece->orchestras)->toHaveCount(2)
        ->each->toBeInstanceOf(Orchestra::class);
});

it('orchestras only returns current usages (tot is null)', function () {
    $piece = Piece::factory()->create();
    $current = Orchestra::factory()->create();
    $historical = Orchestra::factory()->create();

    $piece->orchestraUsages()->create(['orchestra_id' => $current->id]);
    $piece->orchestraUsages()->create(['orchestra_id' => $historical->id, 'tot' => '2025-01-01']);

    expect($piece->orchestras)->toHaveCount(1);
    expect($piece->orchestras->first()->id)->toBe($current->id);
});

it('has many orchestraUsages', function () {
    $piece = Piece::factory()->create();
    $orchestras = Orchestra::factory(3)->create();

    foreach ($orchestras as $orchestra) {
        $piece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);
    }

    expect($piece->orchestraUsages)->toHaveCount(3)
        ->each->toBeInstanceOf(PieceOrchestra::class);
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
