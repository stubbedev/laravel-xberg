<?php

declare(strict_types=1);

namespace Kontainer\Xberg;

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
     * Extract a single document. Strings are treated as a path/URI.
     */
    public function extract(ExtractInput|string $input, ?ExtractionConfig $config = null): ExtractionResult
    {
        if (is_string($input)) {
            $input = ExtractInput::fromUri($input);
        }

        return XbergCore::extract($input, $config ?? $this->defaultConfig());
    }

    /**
     * @param array<ExtractInput|string> $inputs
     */
    public function extractBatch(array $inputs, ?ExtractionConfig $config = null): ExtractionResult
    {
        $inputs = array_map(
            fn (ExtractInput|string $i) => is_string($i) ? ExtractInput::fromUri($i) : $i,
            $inputs
        );

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

    public function __call(string $method, array $arguments): mixed
    {
        return XbergCore::$method(...$arguments);
    }
}
