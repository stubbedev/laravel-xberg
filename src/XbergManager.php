<?php

declare(strict_types=1);

namespace Stubbedev\Xberg;

use SplFileInfo;
use Xberg\ExtractInput;
use Xberg\ExtractionConfig;
use Xberg\ExtractionResult;
use Xberg\XbergApi;

/**
 * Thin wrapper around \Xberg\XbergApi that applies config/xberg.php defaults.
 *
 * Any method not defined here (list*, register*, unregister*, clear*,
 * mapUrl, ...) is forwarded verbatim to \Xberg\XbergApi via __call.
 */
class XbergManager
{
    public function __construct(private readonly array $config)
    {
    }

    // ponytail: without this, a missing extension surfaces as
    // "Class \Xberg\ExtractInput not found" deep in a request.
    private function assertExtensionLoaded(): void
    {
        if (!extension_loaded('xberg')) {
            throw new \RuntimeException(
                'The xberg extension is not installed. Install it with '.
                '"pie install xberg-io/xberg", or use Xberg::fake() in tests.'
            );
        }
    }

    /**
     * Extract a single document. Accepts a path/URL string, an SplFileInfo
     * (including Laravel UploadedFile), or a prepared ExtractInput.
     */
    public function extract(ExtractInput|string|SplFileInfo $input, ?ExtractionConfig $config = null): ExtractionResult
    {
        $this->assertExtensionLoaded();

        return XbergApi::extract($this->normalize($input), $config ?? $this->config());
    }

    /**
     * Extract a single document and return just its text content.
     */
    public function text(ExtractInput|string|SplFileInfo $input, ?ExtractionConfig $config = null): string
    {
        return $this->extract($input, $config)->getResults()[0]->content;
    }

    /**
     * @param array<ExtractInput|string|SplFileInfo> $inputs
     */
    public function extractBatch(array $inputs, ?ExtractionConfig $config = null): ExtractionResult
    {
        $this->assertExtensionLoaded();

        $inputs = array_map($this->normalize(...), $inputs);

        return XbergApi::extractBatch($inputs, $config ?? $this->config());
    }

    /**
     * Build an ExtractionConfig from config/xberg.php, optionally overridden
     * per call with the same array shape:
     *
     *     Xberg::extract($doc, Xberg::config(['ocr' => ['backend' => 'vlm']]));
     *
     * Built via ExtractionConfig::from_json, so everything not covered here
     * keeps the extension's own defaults. Use the 'native' key to pass any
     * other option in xberg's own (snake_case) schema, e.g.
     * ['native' => ['chunking' => ['max_characters' => 2000]]].
     */
    public function config(array $overrides = []): ExtractionConfig
    {
        $this->assertExtensionLoaded();

        $cfg = array_replace_recursive($this->config, $overrides);

        return ExtractionConfig::from_json(json_encode($this->toNative($cfg), JSON_THROW_ON_ERROR));
    }

    /**
     * @deprecated use config()
     */
    public function defaultConfig(): ExtractionConfig
    {
        return $this->config();
    }

    /**
     * Map the Laravel config shape onto xberg's native config schema.
     */
    private function toNative(array $cfg): array
    {
        $ocr = $cfg['ocr'] ?? [];
        $language = $ocr['language'] ?? 'eng';

        $native = [
            'ocr' => [
                'enabled' => (bool) ($ocr['enabled'] ?? true),
                'backend' => $ocr['backend'] ?? 'tesseract',
                'language' => is_array($language) ? $language : explode('+', $language),
                'auto_rotate' => (bool) ($ocr['auto_rotate'] ?? true),
            ],
            'pdf_options' => [
                'extract_tables' => (bool) ($cfg['extract_tables'] ?? true),
                'extract_images' => (bool) ($cfg['extract_images'] ?? false),
            ],
        ];

        if ($cfg['extract_images'] ?? false) {
            $native['images'] = (object) [];
        }

        if (!empty($cfg['llm']['model'])) {
            $native['ocr']['vlm_config'] = array_filter([
                'model' => $cfg['llm']['model'],
                'api_key' => $cfg['llm']['api_key'] ?? null,
                'base_url' => $cfg['llm']['base_url'] ?? null,
            ]);
        }

        if (($ocr['vlm_fallback'] ?? 'disabled') === 'on_low_quality') {
            $native['ocr']['vlm_fallback'] = [
                'on_low_quality' => [
                    'quality_threshold' => (float) ($ocr['vlm_quality_threshold'] ?? 0.5),
                ],
            ];
        }

        return array_replace_recursive($native, $cfg['native'] ?? []);
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
        $this->assertExtensionLoaded();

        return XbergApi::$method(...$arguments);
    }
}
