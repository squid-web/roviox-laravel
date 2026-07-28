<?php

namespace Roviox\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Roviox\RovioxServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [RovioxServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('roviox.key', 'mb_testkey');
    }
}
