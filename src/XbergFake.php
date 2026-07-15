<?php

declare(strict_types=1);

namespace Stubbedev\Xberg;

use Illuminate\Support\Str;
use PHPUnit\Framework\Assert as PHPUnit;
use SplFileInfo;

/**
 * Test double for the Xberg facade — no native extension required.
 *
 * Duck-types XbergManager: returns stubbed extraction results and records
 * every input so tests can assert against them.
 */
class XbergFake
{
    /** @var array<string, string> pattern => content */
    private array $stubs = [];

    /** @var list<string> recorded input names (uri or filename) */
    private array $extracted = [];

    /**
     * Stub the text returned for inputs matching the pattern (Str::is syntax).
     */
    public function stub(string $pattern, string $content): static
    {
        $this->stubs[$pattern] = $content;

        return $this;
    }

    public function extract(mixed $input, mixed $config = null): object
    {
        return $this->fakeOutput([$this->fakeResult($input)]);
    }

    public function extractBatch(array $inputs, mixed $config = null): object
    {
        return $this->fakeOutput(array_map($this->fakeResult(...), $inputs));
    }

    private function fakeOutput(array $results): object
    {
        return new class($results)
        {
            public function __construct(public readonly array $results)
            {
            }

            public function getResults(): array
            {
                return $this->results;
            }
        };
    }

    public function text(mixed $input, mixed $config = null): string
    {
        return $this->fakeResult($input)->content;
    }

    public function assertExtracted(string $pattern): void
    {
        PHPUnit::assertTrue(
            collect($this->extracted)->contains(fn (string $name) => Str::is($pattern, $name)),
            "Expected an extraction matching [{$pattern}]. Extracted: [".implode(', ', $this->extracted).']'
        );
    }

    public function assertNothingExtracted(): void
    {
        PHPUnit::assertSame([], $this->extracted, 'Expected no extractions.');
    }

    private function fakeResult(mixed $input): object
    {
        $name = $this->nameOf($input);
        $this->extracted[] = $name;

        $content = collect($this->stubs)
            ->first(fn (string $text, string $pattern) => Str::is($pattern, $name), 'Fake extracted content');

        return new class($content)
        {
            public string $mimeType = 'text/plain';

            public function __construct(public string $content)
            {
            }

            public function getContent(): string
            {
                return $this->content;
            }

            public function getMimeType(): string
            {
                return $this->mimeType;
            }

            public function getTables(): array
            {
                return [];
            }

            public function getImages(): array
            {
                return [];
            }

            // getMetadata(), getChunks(), ... — empty defaults
            public function __call(string $method, array $arguments): mixed
            {
                return null;
            }
        };
    }

    private function nameOf(mixed $input): string
    {
        return match (true) {
            $input instanceof \Illuminate\Http\UploadedFile => $input->getClientOriginalName(),
            $input instanceof SplFileInfo => $input->getPathname(),
            is_string($input) => $input,
            default => 'input',
        };
    }

    // ponytail: list*/register*/unregister*/clear* and friends become no-ops
    // in tests; list* returns [] rather than modelling the native registry.
    public function __call(string $method, array $arguments): mixed
    {
        return str_starts_with($method, 'list') ? [] : null;
    }
}
