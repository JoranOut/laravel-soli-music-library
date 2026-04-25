<?php

use App\Models\InstrumentType;
use App\Models\User;
use App\Support\PermissionMatrix;

beforeEach(function () {
    PermissionMatrix::seedDefaults();
});

// ---------------------------------------------------------------------------
// Guest access — redirects to login
// ---------------------------------------------------------------------------

it('redirects guests from instrument aliases index', function () {
    $this->get('/admin/instrument-aliases')->assertRedirect('/auth/redirect');
});

it('redirects guests from instrument aliases update', function () {
    $type = InstrumentType::factory()->create();
    $this->put("/admin/instrument-aliases/{$type->id}", ['aliases' => []])->assertRedirect('/auth/redirect');
});

// ---------------------------------------------------------------------------
// Users without permission — 403
// ---------------------------------------------------------------------------

it('denies users without manage-instrument-aliases permission', function () {
    $user = User::factory()->create();
    $user->assignRole('member');

    $this->actingAs($user)->withSession(['roles' => ['member']])
        ->get('/admin/instrument-aliases')
        ->assertForbidden();
});

it('denies update without manage-instrument-aliases permission', function () {
    $user = User::factory()->create();
    $user->assignRole('member');
    $type = InstrumentType::factory()->create(['aliases' => ['old']]);

    $this->actingAs($user)->withSession(['roles' => ['member']])
        ->put("/admin/instrument-aliases/{$type->id}", ['aliases' => ['hacked']])
        ->assertForbidden();

    expect($type->fresh()->aliases)->toBe(['old']);
});

// ---------------------------------------------------------------------------
// Admin access — index + update
// ---------------------------------------------------------------------------

it('allows admin to view instrument aliases page', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    InstrumentType::factory()->create(['aliases' => ['trumpet']]);

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->get('/admin/instrument-aliases')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/instrument-aliases')
            ->has('instrumentTypes')
        );
});

it('allows admin to update aliases', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $type = InstrumentType::factory()->create(['aliases' => []]);

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->put("/admin/instrument-aliases/{$type->id}", [
            'aliases' => ['Trumpet', ' TRP ', 'trumpet'],
        ])
        ->assertRedirect();

    expect($type->fresh()->aliases)->toBe(['trumpet', 'trp']);
});

it('allows admin to clear all aliases', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $type = InstrumentType::factory()->create(['aliases' => ['trumpet', 'trp']]);

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->put("/admin/instrument-aliases/{$type->id}", [
            'aliases' => [],
        ])
        ->assertRedirect();

    expect($type->fresh()->aliases)->toBe([]);
});

// ---------------------------------------------------------------------------
// Muziekbeheer access — should also have permission
// ---------------------------------------------------------------------------

it('allows muziekbeheer to view instrument aliases page', function () {
    $user = User::factory()->create();
    $user->assignRole('muziekbeheer');

    InstrumentType::factory()->create(['aliases' => ['trp']]);

    $this->actingAs($user)->withSession(['roles' => ['muziekbeheer']])
        ->get('/admin/instrument-aliases')
        ->assertOk();
});

it('allows muziekbeheer to update aliases', function () {
    $user = User::factory()->create();
    $user->assignRole('muziekbeheer');
    $type = InstrumentType::factory()->create(['aliases' => []]);

    $this->actingAs($user)->withSession(['roles' => ['muziekbeheer']])
        ->put("/admin/instrument-aliases/{$type->id}", [
            'aliases' => ['flute'],
        ])
        ->assertRedirect();

    expect($type->fresh()->aliases)->toBe(['flute']);
});
