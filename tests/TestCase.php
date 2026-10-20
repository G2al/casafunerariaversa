<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite' || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Test interrotti: è obbligatorio SQLite in memoria. Rimuovere eventuale config cache prima di eseguire la suite.');
        }

        return $app;
    }
}
