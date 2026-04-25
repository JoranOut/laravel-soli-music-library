<?php

use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use App\Models\Part;
use Illuminate\Database\QueryException;

it('can be created with factory', function () {
    $type = InstrumentType::factory()->create();

    expect($type)->toBeInstanceOf(InstrumentType::class)
        ->and($type->name)->toBeString()
        ->and($type->external_id)->toBeInt();
});

it('has unique external_id', function () {
    InstrumentType::factory()->create(['external_id' => 999]);

    InstrumentType::factory()->create(['external_id' => 999]);
})->throws(QueryException::class);

it('belongs to an instrument family', function () {
    $family = InstrumentFamily::factory()->create();
    $type = InstrumentType::factory()->create(['instrument_family_id' => $family->id]);

    expect($type->instrumentFamily)->toBeInstanceOf(InstrumentFamily::class)
        ->and($type->instrumentFamily->id)->toBe($family->id);
});

it('allows null instrument family', function () {
    $type = InstrumentType::factory()->create(['instrument_family_id' => null]);

    expect($type->instrumentFamily)->toBeNull();
});

it('has many parts', function () {
    $type = InstrumentType::factory()->create();
    Part::factory(2)->create(['instrument_type_id' => $type->id]);

    expect($type->parts)->toHaveCount(2)
        ->each->toBeInstanceOf(Part::class);
});

it('can be soft-deleted', function () {
    $type = InstrumentType::factory()->create();

    $type->delete();

    expect(InstrumentType::find($type->id))->toBeNull()
        ->and(InstrumentType::withTrashed()->find($type->id))->not->toBeNull()
        ->and(InstrumentType::withTrashed()->find($type->id)->deleted_at)->not->toBeNull();
});

it('preserves parts when soft-deleted', function () {
    $type = InstrumentType::factory()->create();
    $part = Part::factory()->create(['instrument_type_id' => $type->id]);

    $type->delete();

    expect(Part::find($part->id))->not->toBeNull();
});

it('can be restored after soft-delete', function () {
    $type = InstrumentType::factory()->create();

    $type->delete();
    $type->restore();

    expect(InstrumentType::find($type->id))->not->toBeNull()
        ->and($type->deleted_at)->toBeNull();
});

it('is excluded from normal queries when soft-deleted', function () {
    InstrumentType::factory()->create();
    $deletedType = InstrumentType::factory()->create();
    $deletedType->delete();

    expect(InstrumentType::count())->toBe(1)
        ->and(InstrumentType::withTrashed()->count())->toBe(2);
});
