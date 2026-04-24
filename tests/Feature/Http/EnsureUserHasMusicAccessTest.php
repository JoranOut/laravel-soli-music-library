<?php

use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Models\Piece;
use App\Models\User;

// ---------------------------------------------------------------------------
// Redirect to login — no roles in session
// ---------------------------------------------------------------------------

it('redirects to login when no roles key in session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/muziekstukken')
        ->assertRedirect('/auth/redirect');
});

it('redirects to login when roles array is empty', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => []])
        ->get('/muziekstukken')
        ->assertRedirect('/auth/redirect');
});

// ---------------------------------------------------------------------------
// Allow editor roles
// ---------------------------------------------------------------------------

it('allows admin role through', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken')
        ->assertOk();
});

it('allows muziekbeheer role through', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['muziekbeheer']])
        ->get('/muziekstukken')
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Allow dirigent role
// ---------------------------------------------------------------------------

it('allows dirigent role through', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get('/muziekstukken')
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Allow members with resolved assignments
// ---------------------------------------------------------------------------

it('allows member with resolved assignments through', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    // Need at least one piece in the member's orchestra for the index to return results
    $piece = Piece::factory()->create();
    $piece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);

    $this->actingAs($user)
        ->withSession([
            'roles' => ['member'],
            'resolved_assignments' => [
                ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
            ],
        ])
        ->get('/muziekstukken')
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Deny members without assignments
// ---------------------------------------------------------------------------

it('denies member without resolved assignments', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->get('/muziekstukken')
        ->assertForbidden();
});

it('denies member with empty resolved assignments array', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'roles' => ['member'],
            'resolved_assignments' => [],
        ])
        ->get('/muziekstukken')
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Deny other non-qualifying roles
// ---------------------------------------------------------------------------

it('denies lid role without assignments', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['lid']])
        ->get('/muziekstukken')
        ->assertForbidden();
});

it('denies vrijwilliger role without assignments', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['vrijwilliger']])
        ->get('/muziekstukken')
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Mixed roles
// ---------------------------------------------------------------------------

it('allows user with mixed roles including editor', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member', 'muziekbeheer']])
        ->get('/muziekstukken')
        ->assertOk();
});

it('allows user with mixed roles including dirigent', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member', 'dirigent']])
        ->get('/muziekstukken')
        ->assertOk();
});

it('allows member with assignments even when combined with other non-qualifying roles', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    $piece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);

    $this->actingAs($user)
        ->withSession([
            'roles' => ['member', 'vrijwilliger'],
            'resolved_assignments' => [
                ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
            ],
        ])
        ->get('/muziekstukken')
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Applies to show route too
// ---------------------------------------------------------------------------

it('allows dirigent to access show route', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk();
});

it('denies member without assignments on show route', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertForbidden();
});

it('allows member with assignments on show route', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'roles' => ['member'],
            'resolved_assignments' => [
                ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
            ],
        ])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk();
});
