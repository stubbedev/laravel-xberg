<?php

declare(strict_types=1);

namespace Stubbedev\Xberg\Tests;

use Illuminate\Http\UploadedFile;
use Orchestra\Testbench\TestCase;
use Stubbedev\Xberg\Facades\Xberg;
use Stubbedev\Xberg\XbergServiceProvider;

class FakeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [XbergServiceProvider::class];
    }

    public function test_fake_returns_stubbed_content(): void
    {
        Xberg::fake()->stub('*.pdf', 'pdf text here');

        $this->assertSame('pdf text here', Xberg::text('invoice.pdf'));
        $this->assertSame('pdf text here', Xberg::extract('invoice.pdf')->results[0]->content);
        $this->assertSame('Fake extracted content', Xberg::text('notes.txt'));
    }

    public function test_fake_records_and_asserts_extractions(): void
    {
        $fake = Xberg::fake();

        Xberg::extractBatch(['a.pdf', UploadedFile::fake()->create('b.docx')]);

        $fake->assertExtracted('a.pdf');
        $fake->assertExtracted('*.docx');
    }

    public function test_fake_noops_registry_methods(): void
    {
        Xberg::fake()->assertNothingExtracted();

        $this->assertSame([], Xberg::listOcrBackends());
        $this->assertNull(Xberg::registerOcrBackend(new \stdClass()));
    }
}
