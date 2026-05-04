<?php

use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Models\Part;
use App\Models\Piece;
use App\Models\User;

it('redirects guests from dashboard', function () {
    $this->get('/')->assertRedirect('/auth/redirect');
});

it('shows pieces with no end date on the dashboard', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $family = InstrumentFamily::factory()->create();
    $instrumentType = InstrumentType::factory()->create(['instrument_family_id' => $family->id]);

    $piece = Piece::factory()->create(['title' => 'Active Piece']);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrumentType->id]);
    $orchestra->speelperiodes()->create(['van' => now(), 'piece_id' => $piece->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orchestraGroups', 1)
            ->has('orchestraGroups.0.pieces', 1)
            ->where('orchestraGroups.0.pieces.0.id', $piece->id)
        );
});

it('shows pieces with a future end date on the dashboard', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $family = InstrumentFamily::factory()->create();
    $instrumentType = InstrumentType::factory()->create(['instrument_family_id' => $family->id]);

    $piece = Piece::factory()->create(['title' => 'Future End Date Piece']);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrumentType->id]);
    $orchestra->speelperiodes()->create([
        'van' => now(),
        'piece_id' => $piece->id,
        'tot' => now()->addMonth()->toDateString(),
    ]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orchestraGroups', 1)
            ->has('orchestraGroups.0.pieces', 1)
            ->where('orchestraGroups.0.pieces.0.id', $piece->id)
        );
});

it('hides pieces with a past end date on the dashboard', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $family = InstrumentFamily::factory()->create();
    $instrumentType = InstrumentType::factory()->create(['instrument_family_id' => $family->id]);

    $piece = Piece::factory()->create(['title' => 'Past End Date Piece']);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrumentType->id]);
    $orchestra->speelperiodes()->create([
        'van' => now(),
        'piece_id' => $piece->id,
        'tot' => '2025-01-01',
    ]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orchestraGroups', 1)
            ->has('orchestraGroups.0.pieces', 0)
        );
});
