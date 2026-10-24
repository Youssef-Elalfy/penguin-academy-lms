<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if ($app->configurationIsCached()
            || ! $app->environment('testing')
            || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'penguin_academy_test'
            || config('database.connections.mysql.username') !== 'penguin_test') {
            throw new RuntimeException('Tests require the dedicated MySQL test database and user, with no cached configuration.');
        }

        return $app;
    }
}
