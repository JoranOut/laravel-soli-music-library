<?php

use App\Models\DownloadLog;
use App\Models\Part;
use App\Models\User;

it('can be created', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    $log = DownloadLog::create([
        'user_id' => $user->id,
        'part_id' => $part->id,
        'downloaded_at' => '2026-03-15 14:30:00',
        'ip' => '10.0.0.1',
    ]);

    expect($log)->toBeInstanceOf(DownloadLog::class)
        ->and($log->ip)->toBe('10.0.0.1');
});

it('has no timestamps', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    $log = DownloadLog::create([
        'user_id' => $user->id,
        'part_id' => $part->id,
        'downloaded_at' => now(),
        'ip' => '10.0.0.1',
    ]);

    $log->refresh();

    expect($log->created_at)->toBeNull()
        ->and($log->updated_at)->toBeNull();
});

it('casts downloaded_at to datetime', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    $log = DownloadLog::create([
        'user_id' => $user->id,
        'part_id' => $part->id,
        'downloaded_at' => '2026-03-15 14:30:00',
        'ip' => '10.0.0.1',
    ]);

    $log->refresh();

    expect($log->downloaded_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    $log = DownloadLog::create([
        'user_id' => $user->id,
        'part_id' => $part->id,
        'downloaded_at' => now(),
        'ip' => '10.0.0.1',
    ]);

    expect($log->user)->toBeInstanceOf(User::class)
        ->and($log->user->id)->toBe($user->id);
});

it('belongs to a part', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    $log = DownloadLog::create([
        'user_id' => $user->id,
        'part_id' => $part->id,
        'downloaded_at' => now(),
        'ip' => '10.0.0.1',
    ]);

    expect($log->part)->toBeInstanceOf(Part::class)
        ->and($log->part->id)->toBe($part->id);
});

it('nullifies user_id when user is deleted', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    $log = DownloadLog::create([
        'user_id' => $user->id,
        'part_id' => $part->id,
        'downloaded_at' => now(),
        'ip' => '10.0.0.1',
    ]);

    $user->delete();

    $log->refresh();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBeNull();
});

it('nullifies part_id when part is deleted', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    $log = DownloadLog::create([
        'user_id' => $user->id,
        'part_id' => $part->id,
        'downloaded_at' => now(),
        'ip' => '10.0.0.1',
    ]);

    $part->delete();

    $log->refresh();

    expect($log)->not->toBeNull()
        ->and($log->part_id)->toBeNull();
});
