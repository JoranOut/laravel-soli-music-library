<?php

use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Models\Part;
use App\Models\Piece;
use App\Models\User;
use App\Services\MusicAccessService;
use Spatie\Permission\Models\Permission;

// ---------------------------------------------------------------------------
// isEditor()
// ---------------------------------------------------------------------------

it('isEditor returns true for admin role', function () {
    session(['roles' => ['admin']]);
    expect(app(MusicAccessService::class)->isEditor())->toBeTrue();
});

it('isEditor returns true for muziekbeheer role', function () {
    session(['roles' => ['muziekbeheer']]);
    expect(app(MusicAccessService::class)->isEditor())->toBeTrue();
});

it('isEditor returns true when editor role mixed with non-editor roles', function () {
    session(['roles' => ['member', 'muziekbeheer', 'vrijwilliger']]);
    expect(app(MusicAccessService::class)->isEditor())->toBeTrue();
});

it('isEditor returns false for non-editor roles', function (string $role) {
    session(['roles' => [$role]]);
    expect(app(MusicAccessService::class)->isEditor())->toBeFalse();
})->with(['member', 'lid', 'dirigent', 'vrijwilliger']);

it('isEditor returns false when no roles in session', function () {
    expect(app(MusicAccessService::class)->isEditor())->toBeFalse();
});

it('isEditor returns false for empty roles array', function () {
    session(['roles' => []]);
    expect(app(MusicAccessService::class)->isEditor())->toBeFalse();
});

// ---------------------------------------------------------------------------
// isDirigent()
// ---------------------------------------------------------------------------

it('isDirigent returns true for dirigent role', function () {
    session(['roles' => ['dirigent']]);
    expect(app(MusicAccessService::class)->isDirigent())->toBeTrue();
});

it('isDirigent returns true when dirigent is among other roles', function () {
    session(['roles' => ['member', 'dirigent']]);
    expect(app(MusicAccessService::class)->isDirigent())->toBeTrue();
});

it('isDirigent returns false for non-dirigent roles', function () {
    session(['roles' => ['admin', 'member']]);
    expect(app(MusicAccessService::class)->isDirigent())->toBeFalse();
});

it('isDirigent returns false when no roles in session', function () {
    expect(app(MusicAccessService::class)->isDirigent())->toBeFalse();
});

it('isDirigent returns false for empty roles array', function () {
    session(['roles' => []]);
    expect(app(MusicAccessService::class)->isDirigent())->toBeFalse();
});

// ---------------------------------------------------------------------------
// getResolvedAssignments()
// ---------------------------------------------------------------------------

it('getResolvedAssignments returns assignments from session', function () {
    $assignments = [
        ['orchestra_id' => 1, 'instrument_type_id' => 2],
        ['orchestra_id' => 3, 'instrument_type_id' => 4],
    ];
    session(['resolved_assignments' => $assignments]);

    expect(app(MusicAccessService::class)->getResolvedAssignments())->toBe($assignments);
});

it('getResolvedAssignments returns empty array when key missing', function () {
    expect(app(MusicAccessService::class)->getResolvedAssignments())->toBe([]);
});

it('getResolvedAssignments returns empty array for empty session value', function () {
    session(['resolved_assignments' => []]);
    expect(app(MusicAccessService::class)->getResolvedAssignments())->toBe([]);
});

// ---------------------------------------------------------------------------
// getOrchestraIds()
// ---------------------------------------------------------------------------

it('getOrchestraIds returns unique orchestra IDs', function () {
    session(['resolved_assignments' => [
        ['orchestra_id' => 1, 'instrument_type_id' => 10],
        ['orchestra_id' => 2, 'instrument_type_id' => 20],
        ['orchestra_id' => 1, 'instrument_type_id' => 30],
    ]]);

    $ids = app(MusicAccessService::class)->getOrchestraIds();
    expect($ids)->toBe([1, 2]);
});

it('getOrchestraIds returns empty array when no assignments', function () {
    expect(app(MusicAccessService::class)->getOrchestraIds())->toBe([]);
});

it('getOrchestraIds returns properly re-indexed array', function () {
    session(['resolved_assignments' => [
        ['orchestra_id' => 5, 'instrument_type_id' => 10],
    ]]);

    $ids = app(MusicAccessService::class)->getOrchestraIds();
    expect($ids)->toBe([5]);
    expect(array_keys($ids))->toBe([0]);
});

// ---------------------------------------------------------------------------
// visibleParts() — download-all permission
// ---------------------------------------------------------------------------

it('visibleParts returns all parts with download-all permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');

    $piece = Piece::factory()->create();
    Part::factory()->count(3)->create(['piece_id' => $piece->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(3);
});

it('visibleParts returns all parts including conductor and non-conductor with download-all', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'is_conductor' => true]);
    Part::factory()->create(['piece_id' => $piece->id, 'is_conductor' => false]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// visibleParts() — download-score permission
// ---------------------------------------------------------------------------

it('visibleParts returns only conductor parts with download-score permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-score partijen');
    $user->givePermissionTo('download-score partijen');

    $piece = Piece::factory()->create();
    Part::factory()->partituur()->create(['piece_id' => $piece->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'is_conductor' => false]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(1);
    expect($parts->first()->is_conductor)->toBeTrue();
});

it('visibleParts returns empty without any download permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $piece = Piece::factory()->create();
    Part::factory()->partituur()->create(['piece_id' => $piece->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'is_conductor' => false]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(0);
});

it('visibleParts returns empty with download-score when no conductor parts exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-score partijen');
    $user->givePermissionTo('download-score partijen');

    $piece = Piece::factory()->create();
    Part::factory()->count(3)->create(['piece_id' => $piece->id, 'is_conductor' => false]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(0);
});

it('visibleParts returns multiple conductor parts with download-score', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-score partijen');
    $user->givePermissionTo('download-score partijen');

    $piece = Piece::factory()->create();
    Part::factory()->partituur()->count(2)->create(['piece_id' => $piece->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'is_conductor' => false]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// visibleParts() — download-assigned permission
// ---------------------------------------------------------------------------

it('visibleParts returns matching instrument parts with download-assigned', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');

    $orchestra = Orchestra::factory()->create();
    $myInstrument = InstrumentType::factory()->create();
    $otherInstrument = InstrumentType::factory()->create();

    session([
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $myInstrument->id],
        ],
    ]);

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $myInstrument->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $otherInstrument->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(1);
    expect($parts->first()->instrument_type_id)->toBe($myInstrument->id);
});

it('visibleParts returns empty for member when piece is not in their orchestra', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');

    $myOrchestra = Orchestra::factory()->create();
    $otherOrchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    session([
        'resolved_assignments' => [
            ['orchestra_id' => $myOrchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ]);

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $otherOrchestra->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrumentType->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(0);
});

it('visibleParts returns empty for member when instrument does not match', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');

    $orchestra = Orchestra::factory()->create();
    $myInstrument = InstrumentType::factory()->create();
    $otherInstrument = InstrumentType::factory()->create();

    session([
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $myInstrument->id],
        ],
    ]);

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $otherInstrument->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(0);
});

it('visibleParts returns empty for member with no assignments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');

    session(['resolved_assignments' => []]);

    $piece = Piece::factory()->create();
    Part::factory()->count(2)->create(['piece_id' => $piece->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(0);
});

it('visibleParts handles member with multiple assignments across orchestras', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');

    $orchestra1 = Orchestra::factory()->create();
    $orchestra2 = Orchestra::factory()->create();
    $instrument1 = InstrumentType::factory()->create();
    $instrument2 = InstrumentType::factory()->create();
    $otherInstrument = InstrumentType::factory()->create();

    session([
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra1->id, 'instrument_type_id' => $instrument1->id],
            ['orchestra_id' => $orchestra2->id, 'instrument_type_id' => $instrument2->id],
        ],
    ]);

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra1->id]);
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra2->id]);

    $part1 = Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrument1->id]);
    $part2 = Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrument2->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $otherInstrument->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(2);
    expect($parts->pluck('id')->sort()->values()->toArray())->toBe([$part1->id, $part2->id]);
});

it('visibleParts for member respects orchestra-instrument pairing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');

    $orchestra1 = Orchestra::factory()->create();
    $orchestra2 = Orchestra::factory()->create();
    $instrument = InstrumentType::factory()->create();

    session([
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra1->id, 'instrument_type_id' => $instrument->id],
        ],
    ]);

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra2->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrument->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(0);
});

it('visibleParts for member with piece belonging to multiple orchestras shows parts if at least one matches', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');

    $myOrchestra = Orchestra::factory()->create();
    $otherOrchestra = Orchestra::factory()->create();
    $instrument = InstrumentType::factory()->create();

    session([
        'resolved_assignments' => [
            ['orchestra_id' => $myOrchestra->id, 'instrument_type_id' => $instrument->id],
        ],
    ]);

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $myOrchestra->id]);
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $otherOrchestra->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrument->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(1);
});

it('visibleParts returns empty for piece with no orchestras for member', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');

    $orchestra = Orchestra::factory()->create();
    $instrument = InstrumentType::factory()->create();

    session([
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrument->id],
        ],
    ]);

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrument->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(0);
});

// ---------------------------------------------------------------------------
// visibleParts() — Combined permissions
// ---------------------------------------------------------------------------

it('visibleParts returns conductor + assigned parts with both download-score and download-assigned', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-score partijen');
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo(['download-score partijen', 'download-assigned partijen']);

    $orchestra = Orchestra::factory()->create();
    $myInstrument = InstrumentType::factory()->create();

    session([
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $myInstrument->id],
        ],
    ]);

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $myInstrument->id]);
    Part::factory()->partituur()->create(['piece_id' => $piece->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(2);
});

it('visibleParts excludes conductor parts with only download-assigned', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');

    $orchestra = Orchestra::factory()->create();
    $myInstrument = InstrumentType::factory()->create();

    session([
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $myInstrument->id],
        ],
    ]);

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra->id]);
    Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $myInstrument->id]);
    Part::factory()->partituur()->create(['piece_id' => $piece->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(1);
    expect($parts->first()->is_conductor)->toBeFalse();
});

it('visibleParts returns empty for piece with no parts', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');

    $piece = Piece::factory()->create();

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(0);
});

it('visibleParts loads instrument type relationship', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts->first()->relationLoaded('instrumentType'))->toBeTrue();
    expect($parts->first()->instrumentType->relationLoaded('instrumentFamily'))->toBeTrue();
});

it('visibleParts with download-all supersedes other permissions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');

    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id, 'is_conductor' => false]);
    Part::factory()->partituur()->create(['piece_id' => $piece->id]);

    $parts = app(MusicAccessService::class)->visibleParts($piece);
    expect($parts)->toHaveCount(2);
});
