<?php

use App\Models\DownloadLog;
use App\Models\Part;
use App\Models\User;
use App\Support\PermissionMatrix;

beforeEach(function () {
    PermissionMatrix::seedDefaults();
});

// ---------------------------------------------------------------------------
// Guest access — redirects to login
// ---------------------------------------------------------------------------

it('redirects guests from download logs index', function () {
    $this->get('/admin/download-logs')->assertRedirect('/auth/redirect');
});

// ---------------------------------------------------------------------------
// Users without permission — 403
// ---------------------------------------------------------------------------

it('denies users without view-download-logs permission', function () {
    $user = User::factory()->create();
    $user->assignRole('member');

    $this->actingAs($user)->withSession(['roles' => ['member']])
        ->get('/admin/download-logs')
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Admin access — index
// ---------------------------------------------------------------------------

it('allows admin to view download logs page', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->get('/admin/download-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/download-logs')
            ->has('logs')
        );
});

it('shows download log entries with correct data', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $downloader = User::factory()->create(['name' => 'Test Downloader']);
    $part = Part::factory()->create();

    DownloadLog::create([
        'user_id' => $downloader->id,
        'part_id' => $part->id,
        'downloaded_at' => '2026-05-12 10:00:00',
        'ip_hash' => hash('sha256', '127.0.0.1'),
        'country' => 'NL',
    ]);

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->get('/admin/download-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/download-logs')
            ->has('logs.data', 1)
            ->where('logs.data.0.user_name', 'Test Downloader')
            ->where('logs.data.0.piece_title', $part->piece->title)
            ->where('logs.data.0.country', 'NL')
        );
});
