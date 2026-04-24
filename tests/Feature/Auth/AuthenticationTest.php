<?php

use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect('/auth/redirect');
});

it('allows authenticated users to access the home page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/')
        ->assertOk();
});

it('redirects guests from admin routes to the login page', function () {
    $this->get('/admin/roles')->assertRedirect('/auth/redirect');
});

it('denies non-admin users access to admin routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->get('/admin/roles')
        ->assertForbidden();
});

it('allows admin users to access admin routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get('/admin/roles')
        ->assertOk();
});
