<?php

use App\Models\DownloadLog;
use App\Models\Part;
use App\Models\User;

it('can be created with factory', function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->oidc_sub)->toBeString()
        ->and($user->name)->toBeString()
        ->and($user->email)->toBeString();
});

it('casts oidc_roles to array', function () {
    $user = User::factory()->create(['oidc_roles' => ['member', 'editor']]);

    $user->refresh();

    expect($user->oidc_roles)->toBeArray()
        ->and($user->oidc_roles)->toBe(['member', 'editor']);
});

it('casts last_synced_at to datetime', function () {
    $user = User::factory()->create(['last_synced_at' => '2026-01-15 10:00:00']);

    $user->refresh();

    expect($user->last_synced_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('allows null oidc_roles and last_synced_at', function () {
    $user = User::factory()->create(['oidc_roles' => null, 'last_synced_at' => null]);

    $user->refresh();

    expect($user->oidc_roles)->toBeNull()
        ->and($user->last_synced_at)->toBeNull();
});

it('has many download logs', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    DownloadLog::create([
        'user_id' => $user->id,
        'part_id' => $part->id,
        'downloaded_at' => now(),
        'ip' => '127.0.0.1',
    ]);

    expect($user->downloadLogs)->toHaveCount(1)
        ->and($user->downloadLogs->first())->toBeInstanceOf(DownloadLog::class);
});
