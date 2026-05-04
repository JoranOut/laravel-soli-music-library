<?php

use App\Models\Orchestra;
use App\Models\Piece;
use Illuminate\Database\QueryException;

it('can be created with factory', function () {
    $orchestra = Orchestra::factory()->create();

    expect($orchestra)->toBeInstanceOf(Orchestra::class)
        ->and($orchestra->name)->toBeString()
        ->and($orchestra->external_id)->toBeInt();
});

it('casts is_active to boolean', function () {
    $orchestra = Orchestra::factory()->create(['is_active' => 1]);

    $orchestra->refresh();

    expect($orchestra->is_active)->toBeBool()->toBeTrue();
});

it('has unique external_id', function () {
    Orchestra::factory()->create(['external_id' => 999]);

    Orchestra::factory()->create(['external_id' => 999]);
})->throws(QueryException::class);

it('belongs to many pieces via usages', function () {
    $orchestra = Orchestra::factory()->create();
    $pieces = Piece::factory(3)->create();

    foreach ($pieces as $piece) {
        $orchestra->speelperiodes()->create(['van' => now(), 'piece_id' => $piece->id]);
    }

    expect($orchestra->pieces)->toHaveCount(3)
        ->each->toBeInstanceOf(Piece::class);
});

it('pieces only returns active usages (tot is null or in the future)', function () {
    $orchestra = Orchestra::factory()->create();
    $current = Piece::factory()->create();
    $future = Piece::factory()->create();
    $historical = Piece::factory()->create();

    $orchestra->speelperiodes()->create(['van' => now(), 'piece_id' => $current->id]);
    $orchestra->speelperiodes()->create(['van' => now(), 'piece_id' => $future->id, 'tot' => now()->addMonth()->toDateString()]);
    $orchestra->speelperiodes()->create(['van' => now(), 'piece_id' => $historical->id, 'tot' => '2025-01-01']);

    $pieceIds = $orchestra->pieces->pluck('id')->toArray();
    expect($pieceIds)->toContain($current->id)
        ->toContain($future->id)
        ->not->toContain($historical->id);
});

it('can be soft-deleted', function () {
    $orchestra = Orchestra::factory()->create();

    $orchestra->delete();

    expect(Orchestra::find($orchestra->id))->toBeNull()
        ->and(Orchestra::withTrashed()->find($orchestra->id))->not->toBeNull()
        ->and(Orchestra::withTrashed()->find($orchestra->id)->deleted_at)->not->toBeNull();
});

it('preserves speelperiode links when soft-deleted', function () {
    $orchestra = Orchestra::factory()->create();
    $piece = Piece::factory()->create();
    $orchestra->speelperiodes()->create(['van' => now(), 'piece_id' => $piece->id]);

    $orchestra->delete();

    expect($piece->speelperiodes()->count())->toBe(1);
});

it('is excluded from normal queries when soft-deleted', function () {
    Orchestra::factory()->create();
    $deletedOrchestra = Orchestra::factory()->create();
    $deletedOrchestra->delete();

    expect(Orchestra::count())->toBe(1)
        ->and(Orchestra::withTrashed()->count())->toBe(2);
});
