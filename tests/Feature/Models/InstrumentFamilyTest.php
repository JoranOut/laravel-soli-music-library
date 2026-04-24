<?php

use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use Illuminate\Database\QueryException;

it('can be created with factory', function () {
    $family = InstrumentFamily::factory()->create();

    expect($family)->toBeInstanceOf(InstrumentFamily::class)
        ->and($family->name)->toBeString()
        ->and($family->external_id)->toBeInt();
});

it('has unique external_id', function () {
    InstrumentFamily::factory()->create(['external_id' => 999]);

    InstrumentFamily::factory()->create(['external_id' => 999]);
})->throws(QueryException::class);

it('has many instrument types', function () {
    $family = InstrumentFamily::factory()->create();
    InstrumentType::factory(3)->create(['instrument_family_id' => $family->id]);

    expect($family->instrumentTypes)->toHaveCount(3)
        ->each->toBeInstanceOf(InstrumentType::class);
});

it('can be soft-deleted', function () {
    $family = InstrumentFamily::factory()->create();

    $family->delete();

    expect(InstrumentFamily::find($family->id))->toBeNull()
        ->and(InstrumentFamily::withTrashed()->find($family->id))->not->toBeNull()
        ->and(InstrumentFamily::withTrashed()->find($family->id)->deleted_at)->not->toBeNull();
});

it('is excluded from normal queries when soft-deleted', function () {
    InstrumentFamily::factory()->create();
    $deletedFamily = InstrumentFamily::factory()->create();
    $deletedFamily->delete();

    expect(InstrumentFamily::count())->toBe(1)
        ->and(InstrumentFamily::withTrashed()->count())->toBe(2);
});
