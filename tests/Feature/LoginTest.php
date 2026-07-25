<?php

test('login user with role admin and assert redirect to admin page', function () {
    $this->createAndAuthenticateUserAdmin();

    $response = $this->get('/');

    $this->assertAuthenticated();

    $this->followRedirects($response)->assertViewIs('admin.index');
});

test('login user without customer and assert redirect to customer search page', function () {
    $this->createAndAuthenticateUserWithoutCustomer();

    $response = $this->get('/');

    $this->assertAuthenticated();

    $this->followRedirects($response)->assertViewIs('customer.search');
});

test('login user with role customer and assert redirect to customer dashboard page', function () {
    $this->createAndAuthenticateUserWithCustomer();

    $response = $this->get('/');

    $this->assertAuthenticated();

    $this->followRedirects($response)->assertViewIs('customer.dashboard');
});

test('Techniker wird beim Login direkt (ohne Umweg) zur Kundensuche geleitet', function () {
    $role = \App\Models\Role::factory()->create(['id' => \App\Models\Role::IS_TECHNIKER]);
    $user = \App\Models\User::factory()->create([
        'username' => 'techniker-login-test',
        'password' => bcrypt('password'),
        'role_id' => $role->id,
        'customer_id' => null,
    ]);

    $response = $this->post('/login', [
        'username' => 'techniker-login-test',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    // Direkter Redirect, kein Umweg über "/" -> "/login" (vorheriger Bug: customer_slug existiert nicht)
    $response->assertRedirect(route('customer.search'));

    // Von der Kundensuche aus muss die globale Suche erreichbar sein
    $this->followRedirects($response)->assertSee(route('search.global'), false);
});

test('Kunde wird beim Login direkt zum eigenen Kunden-Dashboard geleitet', function () {
    $role = \App\Models\Role::factory()->create();
    $customer = \App\Models\Customer::factory()->create();
    $user = \App\Models\User::factory()->create([
        'username' => 'kunde-login-test',
        'password' => bcrypt('password'),
        'role_id' => $role->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->post('/login', [
        'username' => 'kunde-login-test',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect('/'.$customer->slug);
});
