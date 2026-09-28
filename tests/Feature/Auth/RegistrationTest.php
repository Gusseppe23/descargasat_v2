<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('registration screen is not found once a user exists', function () {
    User::factory()->create();

    $this->get(route('register'))->assertNotFound();
});

test('nobody else can register once a user exists', function () {
    User::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'Otra Persona',
        'email' => 'otra@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::count())->toBe(1);
});

test('login screen offers to sign up only while there are no users', function () {
    $this->get(route('login'))->assertSee(route('register'));

    User::factory()->create();

    $this->get(route('login'))->assertDontSee(route('register'));
});
