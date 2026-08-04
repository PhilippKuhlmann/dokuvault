<?php

namespace Tests;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Der Testclient sendet von sich aus "Accept-Language: en-us". Seit die
     * Oberflaeche uebersetzbar ist, antwortete sie deshalb englisch, und
     * Zusicherungen auf deutschen Text schlugen fehl. Deutsch ist die
     * Ausgangssprache; Tests, die eine andere brauchen, setzen den Kopf selbst
     * ueber withHeaders().
     */
    protected $defaultHeaders = ['Accept-Language' => 'de-DE,de'];

    protected function createAndAuthenticateUserWithCustomer()
    {
        $role = Role::factory()->create([
            'id' => '123',
        ]);
        $customer = Customer::factory()->create();

        $user = User::factory()->create([
            'role_id' => $role->id,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user);

        return $user;
    }

    protected function createAndAuthenticateUserWithoutCustomer()
    {
        $role = Role::factory()->create([
            'id' => '123',
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        return $user;
    }

    protected function createAndAuthenticateUserAdmin()
    {
        $role = Role::factory()->create([
            'id' => 1,
        ]);

        $customer = Customer::factory()->create();

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        return $user;
    }
}
