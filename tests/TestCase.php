<?php

namespace Tests;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

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
