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
        $orchestra->pieceUsages()->create(['piece_id' => $piece->id]);
    }

    expect($orchestra->pieces)->toHaveCount(3)
        ->each->toBeInstanceOf(Piece::class);
});

it('can be soft-deleted', function () {
    $orchestra = Orchestra::factory()->create();

    $orchestra->delete();

    expect(Orchestra::find($orchestra->id))->toBeNull()
        ->and(Orchestra::withTrashed()->find($orchestra->id))->not->toBeNull()
        ->and(Orchestra::withTrashed()->find($orchestra->id)->deleted_at)->not->toBeNull();
});

it('preserves piece_orchestra links when soft-deleted', function () {
    $orchestra = Orchestra::factory()->create();
    $piece = Piece::factory()->create();
    $orchestra->pieceUsages()->create(['piece_id' => $piece->id]);

    $orchestra->delete();

    expect($piece->orchestraUsages()->count())->toBe(1);
});

it('is excluded from normal queries when soft-deleted', function () {
    Orchestra::factory()->create();
    $deletedOrchestra = Orchestra::factory()->create();
    $deletedOrchestra->delete();

    expect(Orchestra::count())->toBe(1)
        ->and(Orchestra::withTrashed()->count())->toBe(2);
});
