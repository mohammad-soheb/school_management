<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithTenancy;

/**
 * Base class for Laravel feature tests: a clean migrated MySQL database per test (RefreshDatabase),
 * no Vite build required, and the multi-school helpers from InteractsWithTenancy.
 */
abstract class TestCase extends BaseTestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Every simulated HTTP request starts and ends with a fresh TenantContext, exactly like a real PHP request.
     * Without this a school set by one request (or by the test) would leak into the next and hide bugs.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->tenant()->clear();
        $this->app->forgetScopedInstances();

        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            $this->tenant()->clear();
            $this->app->forgetScopedInstances();
        }
    }
}
