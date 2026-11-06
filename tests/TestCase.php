<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        if (
            $app['config']->get('database.default') !== 'pgsql'
            || $app['config']->get('database.connections.pgsql.database') !== 'booking_api_test'
        ) {
            throw new \RuntimeException(
                'Unsafe test database configuration.'
            );
        }

        return $app;
    }
}
