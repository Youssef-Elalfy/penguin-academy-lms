<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;

abstract class ApiTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function token(User $user, array $abilities): string
    {
        return $user->createToken('PHPUnit test', $abilities, now()->addHour())->plainTextToken;
    }

    protected function api(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->json($method, '/api/v1'.$uri, $data, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }
}
