<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * UserFactory's default password hash is generated with the production
     * bcrypt cost, which does not match BCRYPT_ROUNDS=4 used for testing.
     * Always override it so the "hashed" cast does not throw.
     */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'password' => bcrypt('password'),
        ], $attributes));
    }
}
