<?php

use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use App\Models\Part;
use App\Models\User;
use App\Support\PermissionMatrix;

beforeEach(function () {
    PermissionMatrix::seedDefaults();
});

// ---------------------------------------------------------------------------
// Guest access — redirects to login
// ---------------------------------------------------------------------------

it('redirects guests from instrument types index', function () {
    $this->get('/admin/instrument-types')->assertRedirect('/auth/redirect');
});

it('redirects guests from instrument types store', function () {
    $this->post('/admin/instrument-types', ['name' => 'Trumpet', 'instrument_family_id' => 1])
        ->assertRedirect('/auth/redirect');
});

it('redirects guests from instrument types update', function () {
    $type = InstrumentType::factory()->create();
    $this->put("/admin/instrument-types/{$type->id}", ['name' => 'Updated'])
        ->assertRedirect('/auth/redirect');
});

it('redirects guests from instrument types destroy', function () {
    $type = InstrumentType::factory()->create();
    $this->delete("/admin/instrument-types/{$type->id}")
        ->assertRedirect('/auth/redirect');
});

// ---------------------------------------------------------------------------
// Users without permission — 403
// ---------------------------------------------------------------------------

it('denies users without manage-instrument-types permission on index', function () {
    $user = User::factory()->create();
    $user->assignRole('member');

    $this->actingAs($user)->withSession(['roles' => ['member']])
        ->get('/admin/instrument-types')
        ->assertForbidden();
});

it('denies users without manage-instrument-types permission on store', function () {
    $user = User::factory()->create();
    $user->assignRole('member');
    $family = InstrumentFamily::factory()->create();

    $this->actingAs($user)->withSession(['roles' => ['member']])
        ->post('/admin/instrument-types', ['name' => 'Trumpet', 'instrument_family_id' => $family->id])
        ->assertForbidden();

    expect(InstrumentType::where('name', 'Trumpet')->exists())->toBeFalse();
});

it('denies users without manage-instrument-types permission on update', function () {
    $user = User::factory()->create();
    $user->assignRole('member');
    $type = InstrumentType::factory()->create(['name' => 'Original']);

    $this->actingAs($user)->withSession(['roles' => ['member']])
        ->put("/admin/instrument-types/{$type->id}", ['name' => 'Hacked', 'instrument_family_id' => $type->instrument_family_id])
        ->assertForbidden();

    expect($type->fresh()->name)->toBe('Original');
});

it('denies users without manage-instrument-types permission on destroy', function () {
    $user = User::factory()->create();
    $user->assignRole('member');
    $type = InstrumentType::factory()->create();

    $this->actingAs($user)->withSession(['roles' => ['member']])
        ->delete("/admin/instrument-types/{$type->id}")
        ->assertForbidden();

    expect($type->fresh()->deleted_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// Admin access — instrument types CRUD
// ---------------------------------------------------------------------------

it('allows admin to view instrument types page', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    InstrumentType::factory()->create();

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->get('/admin/instrument-types')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/instrument-types')
            ->has('instrumentTypes')
            ->has('families')
        );
});

it('allows admin to create instrument type', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $family = InstrumentFamily::factory()->create();

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->post('/admin/instrument-types', [
            'name' => 'Trumpet',
            'instrument_family_id' => $family->id,
        ])
        ->assertRedirect();

    expect(InstrumentType::where('name', 'Trumpet')->exists())->toBeTrue();
});

it('validates unique name on create', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $family = InstrumentFamily::factory()->create();
    InstrumentType::factory()->create(['name' => 'Trumpet', 'instrument_family_id' => $family->id]);

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->post('/admin/instrument-types', [
            'name' => 'Trumpet',
            'instrument_family_id' => $family->id,
        ])
        ->assertSessionHasErrors('name');
});

it('allows admin to update instrument type', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $type = InstrumentType::factory()->create(['name' => 'Trumpt']);

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->put("/admin/instrument-types/{$type->id}", [
            'name' => 'Trumpet',
            'instrument_family_id' => $type->instrument_family_id,
            'sort_order' => 5,
        ])
        ->assertRedirect();

    $type->refresh();
    expect($type->name)->toBe('Trumpet');
    expect($type->sort_order)->toBe(5);
});

it('allows admin to delete instrument type without parts', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $type = InstrumentType::factory()->create();

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->delete("/admin/instrument-types/{$type->id}")
        ->assertRedirect();

    expect($type->fresh()->deleted_at)->not->toBeNull();
});

it('allows admin to delete instrument type with replacement', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $type = InstrumentType::factory()->create();
    $replacement = InstrumentType::factory()->create();
    $part = Part::factory()->create(['instrument_type_id' => $type->id]);

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->delete("/admin/instrument-types/{$type->id}", [
            'replace_with_id' => $replacement->id,
        ])
        ->assertRedirect();

    expect($type->fresh()->deleted_at)->not->toBeNull();
    expect($part->fresh()->instrument_type_id)->toBe($replacement->id);
});

// ---------------------------------------------------------------------------
// Admin access — instrument families CRUD
// ---------------------------------------------------------------------------

it('allows admin to create instrument family', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->post('/admin/instrument-types/families', ['name' => 'Brass'])
        ->assertRedirect();

    expect(InstrumentFamily::where('name', 'Brass')->exists())->toBeTrue();
});

it('validates unique name on family create', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    InstrumentFamily::factory()->create(['name' => 'Brass']);

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->post('/admin/instrument-types/families', ['name' => 'Brass'])
        ->assertSessionHasErrors('name');
});

it('allows admin to update instrument family', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $family = InstrumentFamily::factory()->create(['name' => 'Bras']);

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->put("/admin/instrument-types/families/{$family->id}", ['name' => 'Brass'])
        ->assertRedirect();

    expect($family->fresh()->name)->toBe('Brass');
});

it('allows admin to delete instrument family without types', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $family = InstrumentFamily::factory()->create();

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->delete("/admin/instrument-types/families/{$family->id}")
        ->assertRedirect();

    expect($family->fresh()->deleted_at)->not->toBeNull();
});

it('prevents deleting instrument family with linked types', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $family = InstrumentFamily::factory()->create();
    InstrumentType::factory()->create(['instrument_family_id' => $family->id]);

    $this->actingAs($user)->withSession(['roles' => ['admin']])
        ->delete("/admin/instrument-types/families/{$family->id}")
        ->assertRedirect()
        ->assertSessionHasErrors('family');

    expect($family->fresh()->deleted_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// Muziekbeheer access — should also have permission
// ---------------------------------------------------------------------------

it('allows muziekbeheer to view instrument types page', function () {
    $user = User::factory()->create();
    $user->assignRole('muziekbeheer');

    $this->actingAs($user)->withSession(['roles' => ['muziekbeheer']])
        ->get('/admin/instrument-types')
        ->assertOk();
});

it('allows muziekbeheer to create instrument type', function () {
    $user = User::factory()->create();
    $user->assignRole('muziekbeheer');
    $family = InstrumentFamily::factory()->create();

    $this->actingAs($user)->withSession(['roles' => ['muziekbeheer']])
        ->post('/admin/instrument-types', [
            'name' => 'Clarinet',
            'instrument_family_id' => $family->id,
        ])
        ->assertRedirect();

    expect(InstrumentType::where('name', 'Clarinet')->exists())->toBeTrue();
});
