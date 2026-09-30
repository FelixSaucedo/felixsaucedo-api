<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $connection = $application['config']->get('database.default');
        $database = $application['config']->get("database.connections.$connection.database");
        $host = $application['config']->get("database.connections.$connection.host");
        $isolated = ($connection === 'sqlite' && $database === ':memory:')
            || ($connection === 'mysql' && $database === 'portfolio_security_test'
                && $host === 'portfolio-security-mysql-test');

        if (!$application->environment('testing') || !$isolated) {
            throw new RuntimeException('Pruebas bloqueadas: requieren SQLite en memoria o el contenedor MySQL dedicado portfolio-security-mysql-test.');
        }

        return $application;
    }
}
