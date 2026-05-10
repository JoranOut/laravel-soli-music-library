<?php

use App\Models\DownloadLog;
use App\Models\InstrumentType;
use App\Models\Part;
use App\Models\Piece;
use App\Models\User;

it('can be created with factory', function () {
    $part = Part::factory()->create();

    expect($part)->toBeInstanceOf(Part::class)
        ->and($part->file_path)->toBeString()
        ->and($part->original_filename)->toBeString();
});

it('casts is_conductor to boolean', function () {
    $part = Part::factory()->create(['is_conductor' => 1]);

    $part->refresh();

    expect($part->is_conductor)->toBeBool()->toBeTrue();
});

it('can use partituur factory state', function () {
    $part = Part::factory()->partituur()->create();

    expect($part->is_conductor)->toBeTrue()
        ->and($part->original_filename)->toBe('partituur.pdf');
});

it('belongs to a piece', function () {
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    expect($part->piece)->toBeInstanceOf(Piece::class)
        ->and($part->piece->id)->toBe($piece->id);
});

it('belongs to an instrument type', function () {
    $type = InstrumentType::factory()->create();
    $part = Part::factory()->create(['instrument_type_id' => $type->id]);

    expect($part->instrumentType)->toBeInstanceOf(InstrumentType::class)
        ->and($part->instrumentType->id)->toBe($type->id);
});

it('has many download logs', function () {
    $part = Part::factory()->create();
    $user = User::factory()->create();

    DownloadLog::create([
        'user_id' => $user->id,
        'part_id' => $part->id,
        'downloaded_at' => now(),
        'ip_hash' => hash('sha256', '192.168.1.1'),
    ]);

    expect($part->downloadLogs)->toHaveCount(1)
        ->and($part->downloadLogs->first())->toBeInstanceOf(DownloadLog::class);
});

it('resolves instrument type even when soft-deleted', function () {
    $type = InstrumentType::factory()->create();
    $part = Part::factory()->create(['instrument_type_id' => $type->id]);

    $type->delete();

    $part->refresh();

    expect($part->instrumentType)->not->toBeNull()
        ->and($part->instrumentType->id)->toBe($type->id)
        ->and($part->instrumentType->trashed())->toBeTrue();
});

it('survives soft-delete of piece', function () {
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $piece->delete();

    expect(Part::find($part->id))->not->toBeNull();
});

it('is cascade-deleted when piece is force-deleted', function () {
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $piece->forceDelete();

    expect(Part::find($part->id))->toBeNull();
});
