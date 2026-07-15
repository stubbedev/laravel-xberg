<?php

declare(strict_types=1);

namespace Stubbedev\Xberg\Tests;

use Orchestra\Testbench\TestCase;
use Stubbedev\Xberg\Facades\Xberg;
use Stubbedev\Xberg\XbergServiceProvider;

/**
 * Runs only where the native xberg extension is installed (CI integration
 * job). Verifies the wrapper against the real extension, not the docs.
 */
class IntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [XbergServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('xberg')) {
            $this->markTestSkipped('xberg extension not installed');
        }
    }

    public function test_extracts_text_from_a_real_file(): void
    {
        file_put_contents(
            $path = sys_get_temp_dir().'/laravel-xberg-integration.txt',
            'hello from laravel-xberg'
        );

        $this->assertStringContainsString('hello from laravel-xberg', Xberg::text($path));

        $output = Xberg::extract($path);
        $this->assertNotEmpty($output->results);

        unlink($path);
    }

    public function test_config_builds_from_laravel_config(): void
    {
        $config = Xberg::config(['ocr' => ['language' => 'eng+deu'], 'extract_images' => true]);

        $this->assertSame(['eng', 'deu'], $config->ocr->language);
        $this->assertSame('tesseract', $config->ocr->backend);
        $this->assertTrue($config->pdfOptions->extractImages);
        $this->assertNotNull($config->images);
    }

    public function test_backends_list(): void
    {
        $this->assertContains('tesseract', Xberg::listOcrBackends());
    }
}
