<?php

use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Models\Part;
use App\Models\Piece;
use App\Models\User;
use Spatie\Permission\Models\Permission;

// ---------------------------------------------------------------------------
// Guest access — every route must redirect to login
// ---------------------------------------------------------------------------

it('redirects guests from index', function () {
    $this->get('/muziekstukken')->assertRedirect('/auth/redirect');
});

it('redirects guests from create', function () {
    $this->get('/muziekstukken/create')->assertRedirect('/auth/redirect');
});

it('redirects guests from store', function () {
    $this->post('/muziekstukken', ['title' => 'Hack'])->assertRedirect('/auth/redirect');
    expect(Piece::count())->toBe(0);
});

it('redirects guests from edit', function () {
    $piece = Piece::factory()->create();
    $this->get("/muziekstukken/{$piece->id}/edit")->assertRedirect('/auth/redirect');
});

it('redirects guests from update', function () {
    $piece = Piece::factory()->create(['title' => 'Original']);
    $this->put("/muziekstukken/{$piece->id}", ['title' => 'Hacked'])->assertRedirect('/auth/redirect');
    expect($piece->fresh()->title)->toBe('Original');
});

it('redirects guests from destroy', function () {
    $piece = Piece::factory()->create();
    $this->delete("/muziekstukken/{$piece->id}")->assertRedirect('/auth/redirect');
    expect(Piece::find($piece->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Non-editor roles — every route must return 403
// ---------------------------------------------------------------------------

it('denies member role on all piece routes', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Original']);

    $session = ['roles' => ['member']];

    $this->actingAs($user)->withSession($session)->get('/muziekstukken')->assertForbidden();
    $this->actingAs($user)->withSession($session)->get('/muziekstukken/create')->assertForbidden();
    $this->actingAs($user)->withSession($session)->post('/muziekstukken', ['title' => 'Hack'])->assertForbidden();
    $this->actingAs($user)->withSession($session)->get("/muziekstukken/{$piece->id}/edit")->assertForbidden();
    $this->actingAs($user)->withSession($session)->put("/muziekstukken/{$piece->id}", ['title' => 'Hack'])->assertForbidden();
    $this->actingAs($user)->withSession($session)->delete("/muziekstukken/{$piece->id}")->assertForbidden();

    expect(Piece::count())->toBe(1);
    expect($piece->fresh()->title)->toBe('Original');
});

it('denies users with no roles in session', function () {
    $user = User::factory()->create(['oidc_roles' => []]);

    $this->actingAs($user)->get('/muziekstukken')->assertForbidden();
    $this->actingAs($user)->post('/muziekstukken', ['title' => 'Hack'])->assertForbidden();
    expect(Piece::count())->toBe(0);
});

it('denies users with empty roles array', function () {
    $user = User::factory()->create(['oidc_roles' => []]);

    $this->actingAs($user)
        ->withSession(['roles' => []])
        ->get('/muziekstukken')
        ->assertForbidden();
});

it('denies non-qualifying roles on read routes', function (string $role) {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Safe']);

    $session = ['roles' => [$role]];

    $this->actingAs($user)->withSession($session)->get('/muziekstukken')->assertForbidden();
    $this->actingAs($user)->withSession($session)->get("/muziekstukken/{$piece->id}")->assertForbidden();
})->with(['member', 'lid', 'vrijwilliger']);

it('denies non-editor roles on create and delete routes', function (string $role) {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Safe']);

    $session = ['roles' => [$role]];

    $this->actingAs($user)->withSession($session)->post('/muziekstukken', ['title' => 'Hack'])->assertForbidden();
    $this->actingAs($user)->withSession($session)->delete("/muziekstukken/{$piece->id}")->assertForbidden();

    expect($piece->fresh()->title)->toBe('Safe');
})->with(['member', 'lid', 'dirigent', 'vrijwilliger']);

it('denies non-editor/non-dirigent roles on edit and update routes', function (string $role) {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Safe']);

    $session = ['roles' => [$role]];

    $this->actingAs($user)->withSession($session)->get("/muziekstukken/{$piece->id}/edit")->assertForbidden();
    $this->actingAs($user)->withSession($session)->put("/muziekstukken/{$piece->id}", ['title' => 'Hack'])->assertForbidden();

    expect($piece->fresh()->title)->toBe('Safe');
})->with(['member', 'lid', 'vrijwilliger']);

it('does not create a piece when non-editor posts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->post('/muziekstukken', [
            'title' => 'Should Not Exist',
            'composer' => 'Hacker',
        ])
        ->assertForbidden();

    expect(Piece::where('title', 'Should Not Exist')->exists())->toBeFalse();
});

it('does not update a piece when non-editor puts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Untouched']);

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->put("/muziekstukken/{$piece->id}", ['title' => 'Tampered'])
        ->assertForbidden();

    expect($piece->fresh()->title)->toBe('Untouched');
});

it('does not delete a piece when non-editor sends delete', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->delete("/muziekstukken/{$piece->id}")
        ->assertForbidden();

    expect(Piece::find($piece->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Allowed editor roles — verify all three work on every route type
// ---------------------------------------------------------------------------

it('allows admin users to access the index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken')
        ->assertOk();
});

it('allows muziekbeheer users to access the index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['muziekbeheer']])
        ->get('/muziekstukken')
        ->assertOk();
});

it('allows all editor roles to create and delete pieces', function (string $role) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => [$role]])
        ->post('/muziekstukken', ['title' => "Piece by {$role}"])
        ->assertRedirect();

    $piece = Piece::where('title', "Piece by {$role}")->first();
    expect($piece)->not->toBeNull();

    $this->actingAs($user)
        ->withSession(['roles' => [$role]])
        ->delete("/muziekstukken/{$piece->id}")
        ->assertRedirect('/muziekstukken');

    expect(Piece::find($piece->id))->toBeNull();
})->with(['admin', 'muziekbeheer']);

it('allows editor with mixed roles including non-editor ones', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member', 'muziekbeheer', 'vrijwilliger']])
        ->get('/muziekstukken')
        ->assertOk();
});

it('lists pieces on the index page', function () {
    $user = User::factory()->create();
    Piece::factory()->count(3)->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('muziekstukken/index')
            ->has('pieces.data', 3)
        );
});

it('filters pieces by search term', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['title' => 'Marching Band']);
    Piece::factory()->create(['title' => 'Symphony No. 5']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?search=Symphony')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('filters pieces by orchestra', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $piece = Piece::factory()->create();
    $piece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);
    Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?orchestra={$orchestra->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('shows the create form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('muziekstukken/create')
            ->has('orchestras')
        );
});

it('creates a piece with orchestras', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post('/muziekstukken', [
            'title' => 'Test Piece',
            'composer' => 'Test Composer',
            'orchestras' => [$orchestra->id],
        ])
        ->assertRedirect();

    $piece = Piece::where('title', 'Test Piece')->first();
    expect($piece)->not->toBeNull();
    expect($piece->composer)->toBe('Test Composer');
    expect($piece->orchestras)->toHaveCount(1);
});

it('validates that title is required on store', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post('/muziekstukken', [
            'title' => '',
        ])
        ->assertSessionHasErrors('title');
});

it('shows the edit form with piece data and instrument types', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken/{$piece->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('muziekstukken/edit')
            ->has('piece')
            ->has('orchestras')
            ->has('instrumentTypes')
        );
});

it('updates piece metadata', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Old Title']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'New Title',
            'composer' => 'New Composer',
        ])
        ->assertRedirect();

    expect($piece->fresh()->title)->toBe('New Title');
    expect($piece->fresh()->composer)->toBe('New Composer');
});

it('deletes a piece and its parts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->delete("/muziekstukken/{$piece->id}")
        ->assertRedirect('/muziekstukken');

    expect(Piece::find($piece->id))->toBeNull();
});

it('redirects guests from show', function () {
    $piece = Piece::factory()->create();
    $this->get("/muziekstukken/{$piece->id}")->assertRedirect('/auth/redirect');
});

// ---------------------------------------------------------------------------
// canEdit prop — verify it's returned based on role
// ---------------------------------------------------------------------------

it('returns canEdit true for editors on index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canEdit', true)
        );
});

it('returns canEdit false for dirigent on index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canEdit', false)
        );
});

it('returns canEdit true for dirigent on show', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canEdit', true)
        );
});

// ---------------------------------------------------------------------------
// Dirigent access — can read, cannot CRUD
// ---------------------------------------------------------------------------

it('allows dirigent to access index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get('/muziekstukken')
        ->assertOk();
});

it('allows dirigent to access show', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk();
});

it('dirigent sees all pieces on index', function () {
    $user = User::factory()->create();
    Piece::factory()->count(3)->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 3)
        );
});

it('show returns only conductor parts for dirigent with download-score permission', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-score partijen');
    $user->givePermissionTo('download-score partijen');

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'is_conductor' => true]);
    Part::factory()->create(['piece_id' => $piece->id, 'is_conductor' => false]);

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parts', 1)
            ->where('parts.0.is_conductor', true)
        );
});

// ---------------------------------------------------------------------------
// Member access — can read own orchestras/instruments only
// ---------------------------------------------------------------------------

it('allows member with resolved assignments to access index', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/muziekstukken')
        ->assertOk();
});

it('member sees only their orchestra pieces on index', function () {
    $user = User::factory()->create();
    $myOrchestra = Orchestra::factory()->create();
    $otherOrchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $myPiece = Piece::factory()->create();
    $myPiece->orchestraUsages()->create(['orchestra_id' => $myOrchestra->id]);

    $otherPiece = Piece::factory()->create();
    $otherPiece->orchestraUsages()->create(['orchestra_id' => $otherOrchestra->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $myOrchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('show returns only matching instrument parts for member', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');
    $orchestra = Orchestra::factory()->create();
    $myInstrument = InstrumentType::factory()->create();
    $otherInstrument = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    $piece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $myInstrument->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $otherInstrument->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $myInstrument->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parts', 1)
            ->where('parts.0.instrument_type_id', $myInstrument->id)
        );
});

it('show returns all parts for editors', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    Part::factory()->count(3)->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parts', 3)
        );
});

it('show returns download_url for each visible part', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parts', 1)
            ->where('parts.0.download_url', fn ($url) => str_contains($url, '/parts/') && str_contains($url, 'signature='))
        );
});

it('returns canEdit true for editors on show', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canEdit', true)
        );
});

it('returns canEdit false for member on index', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'roles' => ['member'],
            'resolved_assignments' => [
                ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
            ],
        ])
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canEdit', false)
        );
});

it('member with multiple orchestras sees pieces from all their orchestras', function () {
    $user = User::factory()->create();
    $orchestra1 = Orchestra::factory()->create();
    $orchestra2 = Orchestra::factory()->create();
    $otherOrchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $piece1 = Piece::factory()->create();
    $piece1->orchestraUsages()->create(['orchestra_id' => $orchestra1->id]);

    $piece2 = Piece::factory()->create();
    $piece2->orchestraUsages()->create(['orchestra_id' => $orchestra2->id]);

    $otherPiece = Piece::factory()->create();
    $otherPiece->orchestraUsages()->create(['orchestra_id' => $otherOrchestra->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra1->id, 'instrument_type_id' => $instrumentType->id],
            ['orchestra_id' => $orchestra2->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 2)
        );
});

it('member sees piece belonging to multiple orchestras if at least one matches', function () {
    $user = User::factory()->create();
    $myOrchestra = Orchestra::factory()->create();
    $otherOrchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    $piece->orchestraUsages()->create(['orchestra_id' => $myOrchestra->id]);
    $piece->orchestraUsages()->create(['orchestra_id' => $otherOrchestra->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $myOrchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('member search filter still works within their orchestras', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $matchingPiece = Piece::factory()->create(['title' => 'Symphony']);
    $matchingPiece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);

    $nonMatchingPiece = Piece::factory()->create(['title' => 'March']);
    $nonMatchingPiece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/muziekstukken?search=Symphony')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('member sees no pieces when they have no matching orchestras', function () {
    $user = User::factory()->create();
    $myOrchestra = Orchestra::factory()->create();
    $otherOrchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    // Piece only belongs to the other orchestra
    $piece = Piece::factory()->create();
    $piece->orchestraUsages()->create(['orchestra_id' => $otherOrchestra->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $myOrchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 0)
        );
});

it('show returns empty parts for dirigent when piece has no conductor parts', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-score partijen');
    $user->givePermissionTo('download-score partijen');

    $piece = Piece::factory()->create();
    Part::factory()->count(2)->create(['piece_id' => $piece->id, 'is_conductor' => false]);

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parts', 0)
        );
});

it('show returns empty parts for member when piece is not in their orchestra', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');
    $myOrchestra = Orchestra::factory()->create();
    $otherOrchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    $piece->orchestraUsages()->create(['orchestra_id' => $otherOrchestra->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrumentType->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $myOrchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parts', 0)
        );
});

it('orchestra filter excludes past usages by default', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();

    $currentPiece = Piece::factory()->create();
    $currentPiece->orchestraUsages()->create(['orchestra_id' => $orchestra->id, 'tot' => null]);

    $pastPiece = Piece::factory()->create();
    $pastPiece->orchestraUsages()->create(['orchestra_id' => $orchestra->id, 'tot' => '2024-01-01']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?orchestra={$orchestra->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('orchestra filter includes past usages when include_past_usages is set', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();

    $currentPiece = Piece::factory()->create();
    $currentPiece->orchestraUsages()->create(['orchestra_id' => $orchestra->id, 'tot' => null]);

    $pastPiece = Piece::factory()->create();
    $pastPiece->orchestraUsages()->create(['orchestra_id' => $orchestra->id, 'tot' => '2024-01-01']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?orchestra={$orchestra->id}&include_past_usages=1")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 2)
        );
});

it('dirigent can use orchestra filter on index', function () {
    $user = User::factory()->create();
    $orchestra1 = Orchestra::factory()->create();
    $orchestra2 = Orchestra::factory()->create();

    $piece1 = Piece::factory()->create();
    $piece1->orchestraUsages()->create(['orchestra_id' => $orchestra1->id]);

    $piece2 = Piece::factory()->create();
    $piece2->orchestraUsages()->create(['orchestra_id' => $orchestra2->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get("/muziekstukken?orchestra={$orchestra1->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('show parts prop includes instrument_type relationship data', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parts.0.instrument_type')
            ->has('parts.0.instrument_type.instrument_family')
        );
});

// ---------------------------------------------------------------------------
// Dirigent edit access
// ---------------------------------------------------------------------------

it('dirigent can access edit page and receives canEditAllFields false', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get("/muziekstukken/{$piece->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('muziekstukken/edit')
            ->has('piece')
            ->has('orchestras')
            ->where('canEditAllFields', false)
            ->missing('instrumentTypes')
        );
});

it('dirigent update only changes usages and ignores title', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Original Title']);
    $orchestra = Orchestra::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Hacked Title',
            'usages' => [
                ['orchestra_id' => $orchestra->id, 'van' => '2025-01-01'],
            ],
        ])
        ->assertRedirect();

    $piece->refresh();
    expect($piece->title)->toBe('Original Title');
    expect($piece->orchestraUsages)->toHaveCount(1);
    expect($piece->orchestraUsages->first()->orchestra_id)->toBe($orchestra->id);
});

it('member cannot access edit page', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->get("/muziekstukken/{$piece->id}/edit")
        ->assertForbidden();
});

it('editor receives canEditAllFields true and instrumentTypes on edit', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken/{$piece->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canEditAllFields', true)
            ->has('instrumentTypes')
        );
});

it('user with both dirigent and muziekbeheer roles is treated as editor on edit', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent', 'muziekbeheer']])
        ->get("/muziekstukken/{$piece->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canEditAllFields', true)
            ->has('instrumentTypes')
        );
});

it('user with both dirigent and muziekbeheer roles can update all fields', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Old']);

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent', 'muziekbeheer']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'New Title',
            'composer' => 'New Composer',
        ])
        ->assertRedirect();

    expect($piece->fresh()->title)->toBe('New Title');
});

// ---------------------------------------------------------------------------
// Piece update with inline part edits
// ---------------------------------------------------------------------------

it('updates piece metadata and parts in a single request', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Original']);
    $newType = InstrumentType::factory()->create();
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'is_conductor' => false,
        'voice' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Updated Title',
            'parts' => [
                [
                    'id' => $part->id,
                    'instrument_type_id' => $newType->id,
                    'is_conductor' => true,
                    'voice' => 2,
                ],
            ],
        ])
        ->assertRedirect();

    $piece->refresh();
    expect($piece->title)->toBe('Updated Title');

    $part->refresh();
    expect($part->instrument_type_id)->toBe($newType->id);
    expect($part->is_conductor)->toBeTrue();
    expect($part->voice)->toBe(2);
});

it('updates piece without parts when parts key is absent', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Old']);
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'voice' => 1,
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'New',
        ])
        ->assertRedirect();

    expect($piece->fresh()->title)->toBe('New');
    expect($part->fresh()->voice)->toBe(1);
});

it('rejects part update for part belonging to different piece', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $otherPiece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $otherPiece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'parts' => [
                [
                    'id' => $part->id,
                    'instrument_type_id' => $part->instrument_type_id,
                    'is_conductor' => false,
                    'voice' => null,
                ],
            ],
        ])
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Archive / Restore
// ---------------------------------------------------------------------------

it('editor can archive a piece', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post("/muziekstukken/{$piece->id}/archive")
        ->assertRedirect('/muziekstukken');

    expect(Piece::find($piece->id))->toBeNull();
    expect(Piece::withTrashed()->find($piece->id)->deleted_at)->not->toBeNull();
});

it('archived piece is hidden from index', function () {
    $user = User::factory()->create();
    $visible = Piece::factory()->create();
    $archived = Piece::factory()->create();
    $archived->delete();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('editor can restore an archived piece', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $piece->delete();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post("/muziekstukken/{$piece->id}/restore")
        ->assertRedirect("/muziekstukken/{$piece->id}/edit");

    expect(Piece::find($piece->id))->not->toBeNull();
    expect(Piece::find($piece->id)->deleted_at)->toBeNull();
});

it('dirigent cannot archive a piece', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->post("/muziekstukken/{$piece->id}/archive")
        ->assertForbidden();

    expect(Piece::find($piece->id))->not->toBeNull();
});

it('member cannot archive a piece', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->post("/muziekstukken/{$piece->id}/archive")
        ->assertForbidden();

    expect(Piece::find($piece->id))->not->toBeNull();
});

it('edit page is accessible for archived pieces by editors', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $piece->delete();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken/{$piece->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('muziekstukken/edit')
            ->has('piece')
            ->where('canArchive', true)
        );
});

it('edit page returns canArchive false for dirigent', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get("/muziekstukken/{$piece->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canArchive', false)
        );
});

it('destroy force-deletes an archived piece', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $piece->delete();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->delete("/muziekstukken/{$piece->id}")
        ->assertRedirect('/muziekstukken');

    expect(Piece::withTrashed()->find($piece->id))->toBeNull();
});

// ---------------------------------------------------------------------------
// Index filters — verify all filter parameters work correctly
// ---------------------------------------------------------------------------

it('filters pieces by composer', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['composer' => 'Bach']);
    Piece::factory()->create(['composer' => 'Mozart']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?composer=Bach')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('filters pieces by arranger', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['arranger' => 'Jan de Haan']);
    Piece::factory()->create(['arranger' => 'Jacob de Haan']);
    Piece::factory()->create(['arranger' => null]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?arranger='.urlencode('Jan de Haan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('filters pieces by publisher', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['publisher' => 'De Haske']);
    Piece::factory()->create(['publisher' => 'Molenaar']);
    Piece::factory()->create(['publisher' => null]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?publisher='.urlencode('De Haske'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('filters pieces by music type', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['music_type' => 'Concert']);
    Piece::factory()->create(['music_type' => 'Loopmars']);
    Piece::factory()->create(['music_type' => null]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?music_type=Concert')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('filters pieces by genre', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['genre' => ['Pop', 'Jazz']]);
    Piece::factory()->create(['genre' => ['Klassiek']]);
    Piece::factory()->create(['genre' => null]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?genre=Pop')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('genre filter matches pieces that contain the genre in their array', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['genre' => ['Jazz', 'Pop', 'Modern']]);
    Piece::factory()->create(['genre' => ['Pop', 'Klassiek']]);
    Piece::factory()->create(['genre' => ['Klassiek']]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?genre=Pop')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 2)
        );
});

it('filters pieces by status', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['status' => 'analoog']);
    Piece::factory()->create(['status' => 'digitaal']);
    Piece::factory()->create(['status' => 'besteld']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?status=analoog')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('returns statuses in filterOptions on index', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['status' => 'analoog']);
    Piece::factory()->create(['status' => 'digitaal']);
    Piece::factory()->create(['status' => 'analoog']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('filterOptions.statuses', 2)
        );
});

it('returns status filter value in filters prop', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?status=digitaal')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'digitaal')
        );
});

it('stores a piece with status', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post('/muziekstukken', [
            'title' => 'New Piece',
            'status' => 'digitaal',
        ])
        ->assertRedirect();

    $piece = Piece::where('title', 'New Piece')->first();
    expect($piece)->not->toBeNull();
    expect($piece->status)->toBe('digitaal');
});

it('stores a piece with default status when not provided', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post('/muziekstukken', [
            'title' => 'Default Status Piece',
        ])
        ->assertRedirect();

    $piece = Piece::where('title', 'Default Status Piece')->first();
    expect($piece)->not->toBeNull();
    expect($piece->status)->toBe('besteld');
});

it('updates piece status', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'besteld']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
        ])
        ->assertRedirect();

    expect($piece->fresh()->status)->toBe('analoog');
});

it('combines status filter with other filters', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['composer' => 'Bach', 'status' => 'analoog']);
    Piece::factory()->create(['composer' => 'Bach', 'status' => 'digitaal']);
    Piece::factory()->create(['composer' => 'Mozart', 'status' => 'analoog']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?composer=Bach&status=analoog')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('show page returns piece with status', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['status' => 'analoog']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('piece.status', 'analoog')
        );
});

it('filters pieces by difficulty', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['difficulty' => 'easy']);
    Piece::factory()->create(['difficulty' => 'hard']);
    Piece::factory()->create(['difficulty' => null]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?difficulty=easy')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('filters pieces by buy date from', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['buy_date' => '2024-01-15']);
    Piece::factory()->create(['buy_date' => '2023-06-01']);
    Piece::factory()->create(['buy_date' => null]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?buy_date_from=2024-01-01')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('filters pieces by buy date to', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['buy_date' => '2023-06-01']);
    Piece::factory()->create(['buy_date' => '2024-01-15']);
    Piece::factory()->create(['buy_date' => null]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?buy_date_to=2023-12-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('filters pieces by buy date range', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['buy_date' => '2023-01-01']);
    Piece::factory()->create(['buy_date' => '2023-06-15']);
    Piece::factory()->create(['buy_date' => '2024-01-01']);
    Piece::factory()->create(['buy_date' => null]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?buy_date_from=2023-03-01&buy_date_to=2023-12-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('filters pieces by instruments', function () {
    $user = User::factory()->create();
    $instrumentType = InstrumentType::factory()->create();
    $otherInstrumentType = InstrumentType::factory()->create();

    $piece1 = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $instrumentType->id]);

    $piece2 = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece2->id, 'instrument_type_id' => $otherInstrumentType->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$instrumentType->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('combines multiple filters', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['composer' => 'Bach', 'difficulty' => 'hard']);
    Piece::factory()->create(['composer' => 'Bach', 'difficulty' => 'easy']);
    Piece::factory()->create(['composer' => 'Mozart', 'difficulty' => 'hard']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?composer=Bach&difficulty=hard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('combines search with more filters', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['title' => 'Symphony No. 5', 'composer' => 'Beethoven', 'difficulty' => 'hard']);
    Piece::factory()->create(['title' => 'Symphony No. 9', 'composer' => 'Beethoven', 'difficulty' => 'easy']);
    Piece::factory()->create(['title' => 'March of Glory', 'composer' => 'Beethoven', 'difficulty' => 'hard']);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?search=Symphony&composer=Beethoven&difficulty=hard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('returns filterOptions on index', function () {
    $user = User::factory()->create();
    Piece::factory()->create([
        'composer' => 'Bach',
        'arranger' => 'Smith',
        'publisher' => 'Publisher A',
        'music_type' => 'Concert',
        'difficulty' => 'easy',
        'genre' => ['Pop', 'Jazz'],
        'status' => 'analoog',
    ]);
    Piece::factory()->create([
        'composer' => 'Mozart',
        'arranger' => null,
        'publisher' => null,
        'music_type' => null,
        'difficulty' => null,
        'genre' => null,
        'status' => 'digitaal',
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('filterOptions.composers', 2)
            ->has('filterOptions.arrangers', 1)
            ->has('filterOptions.publishers', 1)
            ->has('filterOptions.musicTypes', 8)
            ->has('filterOptions.genres', 28)
            ->has('filterOptions.difficulties', 1)
            ->has('filterOptions.statuses', 2)
        );
});

it('returns current filter values in filters prop', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/muziekstukken?composer=Bach&difficulty=easy&buy_date_from=2024-01-01')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.composer', 'Bach')
            ->where('filters.difficulty', 'easy')
            ->where('filters.buy_date_from', '2024-01-01')
        );
});

it('member can use more filters within their orchestras', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $bachPiece = Piece::factory()->create(['composer' => 'Bach']);
    $bachPiece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);

    $mozartPiece = Piece::factory()->create(['composer' => 'Mozart']);
    $mozartPiece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/muziekstukken?composer=Bach')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('dirigent can use more filters on index', function () {
    $user = User::factory()->create();
    Piece::factory()->create(['music_type' => 'Concert']);
    Piece::factory()->create(['music_type' => 'Loopmars']);

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get('/muziekstukken?music_type=Concert')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

// ---------------------------------------------------------------------------
// Matrix parts — fileless parts for non-digital pieces
// ---------------------------------------------------------------------------

it('creates fileless parts via matrix_parts for analoog piece', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    $type1 = InstrumentType::factory()->create();
    $type2 = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type1->id, 'is_conductor' => false, 'amount_bought' => 3],
                ['instrument_type_id' => $type2->id, 'is_conductor' => false, 'amount_bought' => 5],
            ],
        ])
        ->assertRedirect();

    $parts = $piece->parts()->whereNull('file_path')->get();
    expect($parts)->toHaveCount(2);
    expect($parts->firstWhere('instrument_type_id', $type1->id)->amount_bought)->toBe(3);
    expect($parts->firstWhere('instrument_type_id', $type2->id)->amount_bought)->toBe(5);
});

it('creates a fileless conductor part via matrix_parts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'besteld']);
    $type = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'besteld',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => true, 'amount_bought' => 2],
            ],
        ])
        ->assertRedirect();

    $part = $piece->parts()->whereNull('file_path')->where('is_conductor', true)->first();
    expect($part)->not->toBeNull();
    expect($part->amount_bought)->toBe(2);
    expect($part->original_filename)->toBeNull();
});

it('updates amount on existing fileless part', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    Part::factory()->fileless()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $type->id,
        'is_conductor' => false,
        'amount_bought' => 3,
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'amount_bought' => 7],
            ],
        ])
        ->assertRedirect();

    $part = $piece->parts()->whereNull('file_path')->first();
    expect($part->amount_bought)->toBe(7);
    expect($piece->parts()->whereNull('file_path')->count())->toBe(1);
});

it('deletes fileless part when amount set to zero', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    Part::factory()->fileless()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $type->id,
        'is_conductor' => false,
        'amount_bought' => 5,
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'amount_bought' => 0],
            ],
        ])
        ->assertRedirect();

    expect($piece->parts()->whereNull('file_path')->count())->toBe(0);
});

it('ignores matrix_parts when status is digitaal', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'digitaal']);
    $type = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'digitaal',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'amount_bought' => 3],
            ],
        ])
        ->assertRedirect();

    expect($piece->parts()->count())->toBe(0);
});

it('processes matrix_parts for all non-digital statuses', function (string $status) {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => $status]);
    $type = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => $status,
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'amount_bought' => 2],
            ],
        ])
        ->assertRedirect();

    expect($piece->parts()->whereNull('file_path')->count())->toBe(1);
})->with(['besteld', 'analoog']);

it('matrix_parts does not affect file-based parts', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    $filePart = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $type->id,
        'is_conductor' => false,
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'amount_bought' => 0],
            ],
        ])
        ->assertRedirect();

    // File-based part should still exist
    expect(Part::find($filePart->id))->not->toBeNull();
    expect(Part::find($filePart->id)->file_path)->not->toBeNull();
});

it('show returns null download_url for fileless parts', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create(['status' => 'analoog']);
    Part::factory()->fileless()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken/{$piece->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parts', 1)
            ->where('parts.0.download_url', null)
        );
});

// ---------------------------------------------------------------------------
// Instrument voice filter — new ID:VOICE format on index
// ---------------------------------------------------------------------------

it('filters pieces by instrument with voice format (id:1)', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();
    $otherType = InstrumentType::factory()->create();

    $piece1 = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $type->id, 'voice' => 1]);

    $piece2 = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece2->id, 'instrument_type_id' => $otherType->id, 'voice' => 1]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$type->id}:1")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('voice filter 1 matches parts with null voice', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => null]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$type->id}:1")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('voice filter 2 excludes pieces with only voice 1', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$type->id}:2")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 0)
        );
});

it('voice filter 2 matches pieces with voice 2', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 2]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$type->id}:2")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('voice filter 3 matches pieces with voice 3', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 2]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 3]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$type->id}:3")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('voice filter 3 excludes pieces with only voice 2', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 2]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$type->id}:3")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 0)
        );
});

it('voice filter 2 excludes pieces with null voice', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => null]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$type->id}:2")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 0)
        );
});

it('multiple instrument voice filters use AND logic', function () {
    $user = User::factory()->create();
    $clarinet = InstrumentType::factory()->create();
    $flute = InstrumentType::factory()->create();

    // Piece with both instruments
    $both = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $both->id, 'instrument_type_id' => $clarinet->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $both->id, 'instrument_type_id' => $flute->id, 'voice' => 1]);

    // Piece with only clarinet
    $clarinetOnly = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $clarinetOnly->id, 'instrument_type_id' => $clarinet->id, 'voice' => 1]);

    // Piece with only flute
    $fluteOnly = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $fluteOnly->id, 'instrument_type_id' => $flute->id, 'voice' => 1]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$clarinet->id}:1,{$flute->id}:1")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('multiple instruments with different voice levels', function () {
    $user = User::factory()->create();
    $clarinet = InstrumentType::factory()->create();
    $trumpet = InstrumentType::factory()->create();

    // Piece: clarinet v1+v2+v3, trumpet v1+v2
    $piece1 = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $clarinet->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $clarinet->id, 'voice' => 2]);
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $clarinet->id, 'voice' => 3]);
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $trumpet->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $trumpet->id, 'voice' => 2]);

    // Piece: clarinet v1+v2 only, trumpet v1+v2+v3
    $piece2 = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece2->id, 'instrument_type_id' => $clarinet->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece2->id, 'instrument_type_id' => $clarinet->id, 'voice' => 2]);
    Part::factory()->create(['piece_id' => $piece2->id, 'instrument_type_id' => $trumpet->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece2->id, 'instrument_type_id' => $trumpet->id, 'voice' => 2]);
    Part::factory()->create(['piece_id' => $piece2->id, 'instrument_type_id' => $trumpet->id, 'voice' => 3]);

    // Filter: clarinet >= 3 AND trumpet >= 2 → only piece1
    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$clarinet->id}:3,{$trumpet->id}:2")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('legacy instrument filter format without colon still works', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id]);

    Piece::factory()->create();

    // Legacy format: just the ID without :voice
    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$type->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('voice filter combined with other filters', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece1 = Piece::factory()->create(['composer' => 'Bach']);
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $type->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $type->id, 'voice' => 2]);

    $piece2 = Piece::factory()->create(['composer' => 'Mozart']);
    Part::factory()->create(['piece_id' => $piece2->id, 'instrument_type_id' => $type->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece2->id, 'instrument_type_id' => $type->id, 'voice' => 2]);

    $piece3 = Piece::factory()->create(['composer' => 'Bach']);
    Part::factory()->create(['piece_id' => $piece3->id, 'instrument_type_id' => $type->id, 'voice' => 1]);

    // Bach + at least 2 voices → only piece1
    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?composer=Bach&instruments={$type->id}:2")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('voice filter with search', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece1 = Piece::factory()->create(['title' => 'Symphony No. 5']);
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $type->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece1->id, 'instrument_type_id' => $type->id, 'voice' => 2]);

    $piece2 = Piece::factory()->create(['title' => 'Symphony No. 9']);
    Part::factory()->create(['piece_id' => $piece2->id, 'instrument_type_id' => $type->id, 'voice' => 1]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?search=Symphony&instruments={$type->id}:2")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('voice filter returns empty when no pieces match', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$type->id}:5")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 0)
        );
});

it('voice filter matches fileless parts too', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create(['status' => 'analoog']);
    Part::factory()->fileless()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1]);
    Part::factory()->fileless()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 2]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/muziekstukken?instruments={$type->id}:2")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('member can use voice filter within their orchestras', function () {
    $user = User::factory()->create();
    $orchestra = Orchestra::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    $piece->orchestraUsages()->create(['orchestra_id' => $orchestra->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 2]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $type->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get("/muziekstukken?instruments={$type->id}:2")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

it('dirigent can use voice filter', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 2]);

    Piece::factory()->create(); // no parts

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get("/muziekstukken?instruments={$type->id}:2")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pieces.data', 1)
        );
});

// ---------------------------------------------------------------------------
// Matrix parts — voice support
// ---------------------------------------------------------------------------

it('creates fileless parts with voice via matrix_parts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    $type = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 1, 'amount_bought' => 3],
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 2, 'amount_bought' => 2],
            ],
        ])
        ->assertRedirect();

    $parts = $piece->parts()->whereNull('file_path')->orderBy('voice')->get();
    expect($parts)->toHaveCount(2);
    expect($parts[0]->voice)->toBe(1);
    expect($parts[0]->amount_bought)->toBe(3);
    expect($parts[1]->voice)->toBe(2);
    expect($parts[1]->amount_bought)->toBe(2);
});

it('creates fileless parts with three voices', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    $type = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 1, 'amount_bought' => 5],
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 2, 'amount_bought' => 3],
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 3, 'amount_bought' => 1],
            ],
        ])
        ->assertRedirect();

    $parts = $piece->parts()->whereNull('file_path')->orderBy('voice')->get();
    expect($parts)->toHaveCount(3);
    expect($parts[0]->voice)->toBe(1);
    expect($parts[0]->amount_bought)->toBe(5);
    expect($parts[1]->voice)->toBe(2);
    expect($parts[1]->amount_bought)->toBe(3);
    expect($parts[2]->voice)->toBe(3);
    expect($parts[2]->amount_bought)->toBe(1);
});

it('removes higher voices when voice 2 set to zero', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    Part::factory()->fileless()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1, 'amount_bought' => 5]);
    Part::factory()->fileless()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 2, 'amount_bought' => 3]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 1, 'amount_bought' => 5],
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 2, 'amount_bought' => 0],
            ],
        ])
        ->assertRedirect();

    $parts = $piece->parts()->whereNull('file_path')->get();
    expect($parts)->toHaveCount(1);
    expect($parts->first()->voice)->toBe(1);
    expect($parts->first()->amount_bought)->toBe(5);
});

it('updates amounts per voice correctly', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    Part::factory()->fileless()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1, 'amount_bought' => 3]);
    Part::factory()->fileless()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 2, 'amount_bought' => 2]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 1, 'amount_bought' => 7],
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 2, 'amount_bought' => 4],
            ],
        ])
        ->assertRedirect();

    $parts = $piece->parts()->whereNull('file_path')->orderBy('voice')->get();
    expect($parts)->toHaveCount(2);
    expect($parts[0]->amount_bought)->toBe(7);
    expect($parts[1]->amount_bought)->toBe(4);
});

it('conductor matrix part has null voice', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    $type = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => true, 'voice' => null, 'amount_bought' => 2],
            ],
        ])
        ->assertRedirect();

    $part = $piece->parts()->whereNull('file_path')->where('is_conductor', true)->first();
    expect($part)->not->toBeNull();
    expect($part->voice)->toBeNull();
    expect($part->amount_bought)->toBe(2);
});

it('mixed voices and conductor in single matrix_parts request', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    $clarinet = InstrumentType::factory()->create();
    $flute = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $clarinet->id, 'is_conductor' => true, 'voice' => null, 'amount_bought' => 1],
                ['instrument_type_id' => $clarinet->id, 'is_conductor' => false, 'voice' => 1, 'amount_bought' => 5],
                ['instrument_type_id' => $clarinet->id, 'is_conductor' => false, 'voice' => 2, 'amount_bought' => 3],
                ['instrument_type_id' => $flute->id, 'is_conductor' => false, 'voice' => 1, 'amount_bought' => 4],
            ],
        ])
        ->assertRedirect();

    $parts = $piece->parts()->whereNull('file_path')->get();
    expect($parts)->toHaveCount(4);

    $conductor = $parts->where('is_conductor', true)->first();
    expect($conductor->amount_bought)->toBe(1);
    expect($conductor->voice)->toBeNull();

    $clarinetParts = $parts->where('instrument_type_id', $clarinet->id)->where('is_conductor', false)->sortBy('voice')->values();
    expect($clarinetParts)->toHaveCount(2);
    expect($clarinetParts[0]->voice)->toBe(1);
    expect($clarinetParts[0]->amount_bought)->toBe(5);
    expect($clarinetParts[1]->voice)->toBe(2);
    expect($clarinetParts[1]->amount_bought)->toBe(3);

    $fluteParts = $parts->where('instrument_type_id', $flute->id)->where('is_conductor', false);
    expect($fluteParts)->toHaveCount(1);
    expect($fluteParts->first()->voice)->toBe(1);
    expect($fluteParts->first()->amount_bought)->toBe(4);
});

it('matrix_parts with voices does not affect file-based parts', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    $filePart = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $type->id,
        'is_conductor' => false,
        'voice' => 1,
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 1, 'amount_bought' => 3],
            ],
        ])
        ->assertRedirect();

    // File-based part should still exist
    expect(Part::find($filePart->id))->not->toBeNull();
    expect(Part::find($filePart->id)->file_path)->not->toBeNull();
    // Fileless part should also exist
    expect($piece->parts()->whereNull('file_path')->count())->toBe(1);
});

it('adding a voice to an existing matrix type preserves other voices', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    Part::factory()->fileless()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1, 'amount_bought' => 5]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 1, 'amount_bought' => 5],
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 2, 'amount_bought' => 3],
            ],
        ])
        ->assertRedirect();

    $parts = $piece->parts()->whereNull('file_path')->orderBy('voice')->get();
    expect($parts)->toHaveCount(2);
    expect($parts[0]->voice)->toBe(1);
    expect($parts[0]->amount_bought)->toBe(5);
    expect($parts[1]->voice)->toBe(2);
    expect($parts[1]->amount_bought)->toBe(3);
});

it('setting all voices to zero removes all fileless parts for that type', function () {
    $user = User::factory()->create();
    $type = InstrumentType::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    Part::factory()->fileless()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 1, 'amount_bought' => 5]);
    Part::factory()->fileless()->create(['piece_id' => $piece->id, 'instrument_type_id' => $type->id, 'voice' => 2, 'amount_bought' => 3]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 1, 'amount_bought' => 0],
            ],
        ])
        ->assertRedirect();

    expect($piece->parts()->whereNull('file_path')->count())->toBe(0);
});

it('matrix_parts rejects negative voice', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    $type = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => -1, 'amount_bought' => 3],
            ],
        ])
        ->assertSessionHasErrors('matrix_parts.0.voice');
});

it('matrix_parts rejects voice of zero', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create(['title' => 'Test', 'status' => 'analoog']);
    $type = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}", [
            'title' => 'Test',
            'status' => 'analoog',
            'matrix_parts' => [
                ['instrument_type_id' => $type->id, 'is_conductor' => false, 'voice' => 0, 'amount_bought' => 3],
            ],
        ])
        ->assertSessionHasErrors('matrix_parts.0.voice');
});
