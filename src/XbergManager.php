<?php

declare(strict_types=1);

namespace Stubbedev\Xberg;

use SplFileInfo;
use Xberg\ExtractInput;
use Xberg\ExtractionConfig;
use Xberg\ExtractionResult;
use Xberg\OcrConfig;
use Xberg\Xberg as XbergCore;

/**
 * Thin wrapper around \Xberg\Xberg that applies config/xberg.php defaults.
 *
 * Any method not defined here (list*, register*, unregister*, clear*,
 * mapUrl, ...) is forwarded verbatim to \Xberg\Xberg via __call.
 */
class XbergManager
{
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Extract a single document. Accepts a path/URL string, an SplFileInfo
     * (including Laravel UploadedFile), or a prepared ExtractInput.
     */
    public function extract(ExtractInput|string|SplFileInfo $input, ?ExtractionConfig $config = null): ExtractionResult
    {
        return XbergCore::extract($this->normalize($input), $config ?? $this->defaultConfig());
    }

    /**
     * Extract a single document and return just its text content.
     */
    public function text(ExtractInput|string|SplFileInfo $input, ?ExtractionConfig $config = null): string
    {
        return $this->extract($input, $config)->results[0]->content;
    }

    /**
     * @param array<ExtractInput|string|SplFileInfo> $inputs
     */
    public function extractBatch(array $inputs, ?ExtractionConfig $config = null): ExtractionResult
    {
        $inputs = array_map($this->normalize(...), $inputs);

        return XbergCore::extractBatch($inputs, $config ?? $this->defaultConfig());
    }

    /**
     * The ExtractionConfig built from config/xberg.php.
     */
    public function defaultConfig(): ExtractionConfig
    {
        $ocr = $this->config['ocr'] ?? [];

        return new ExtractionConfig(
            ocr: ($ocr['enabled'] ?? false)
                ? new OcrConfig(
                    backend: $ocr['backend'] ?? 'tesseract',
                    language: $ocr['language'] ?? 'eng',
                )
                : null,
            extractTables: (bool) ($this->config['extract_tables'] ?? true),
            extractImages: (bool) ($this->config['extract_images'] ?? false),
        );
    }

    protected function normalize(ExtractInput|string|SplFileInfo $input): ExtractInput
    {
        if ($input instanceof \Illuminate\Http\UploadedFile) {
            return ExtractInput::fromBytes(
                $input->getContent(),
                $input->getMimeType() ?? 'application/octet-stream',
                $input->getClientOriginalName(),
            );
        }

        if ($input instanceof SplFileInfo) {
            return ExtractInput::fromUri($input->getPathname());
        }

        if (is_string($input)) {
            return ExtractInput::fromUri($input);
        }

        return $input;
    }

    public function __call(string $method, array $arguments): mixed
    {
        return XbergCore::$method(...$arguments);
    }
}
