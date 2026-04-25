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

it('redirects users with no roles in session to login', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/muziekstukken')->assertRedirect('/auth/redirect');
    $this->actingAs($user)->post('/muziekstukken', ['title' => 'Hack'])->assertRedirect('/auth/redirect');
    expect(Piece::count())->toBe(0);
});

it('redirects users with empty roles array to login', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => []])
        ->get('/muziekstukken')
        ->assertRedirect('/auth/redirect');
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
