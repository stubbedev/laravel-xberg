<?php

declare(strict_types=1);

namespace Stubbedev\Xberg\Tests;

use Stubbedev\Xberg\Facades\Xberg;
use Stubbedev\Xberg\XbergManager;
use Stubbedev\Xberg\XbergServiceProvider;
use Orchestra\Testbench\TestCase;

// ponytail: wiring only — extract()/plugin registration need the native
// xberg extension, so they are exercised in an app with the ext installed.
class WiringTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [XbergServiceProvider::class];
    }

    public function test_config_is_merged(): void
    {
        $this->assertSame('tesseract', config('xberg.ocr.backend'));
        $this->assertArrayHasKey('ocr_backends', config('xberg.plugins'));
    }

    public function test_manager_is_a_singleton_and_facade_resolves(): void
    {
        $this->assertInstanceOf(XbergManager::class, $this->app->make('xberg'));
        $this->assertSame($this->app->make('xberg'), $this->app->make(XbergManager::class));
        $this->assertInstanceOf(XbergManager::class, Xberg::getFacadeRoot());
    }
}
