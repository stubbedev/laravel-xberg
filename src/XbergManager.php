<?php

declare(strict_types=1);

namespace Stubbedev\Xberg;

use SplFileInfo;
use Xberg\ExtractInput;
use Xberg\ExtractionConfig;
use Xberg\ExtractionResult;
use Xberg\ImageExtractionConfig;
use Xberg\LlmConfig;
use Xberg\OcrConfig;
use Xberg\PdfConfig;
use Xberg\VlmFallbackPolicy;
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

        return XbergCore::extract($this->normalize($input), $config ?? $this->config());
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
        $this->assertExtensionLoaded();

        $inputs = array_map($this->normalize(...), $inputs);

        return XbergCore::extractBatch($inputs, $config ?? $this->config());
    }

    /**
     * Build an ExtractionConfig from config/xberg.php, optionally overridden
     * per call with the same array shape:
     *
     *     Xberg::extract($doc, Xberg::config(['ocr' => ['backend' => 'vlm']]));
     *
     * Unspecified native options keep the values from the extension's own
     * ExtractionConfig::default(), so new xberg options never break this.
     */
    public function config(array $overrides = []): ExtractionConfig
    {
        $this->assertExtensionLoaded();

        $cfg = array_replace_recursive($this->config, $overrides);

        return new ExtractionConfig(...[
            ...get_object_vars(ExtractionConfig::default()),
            'ocr' => $this->ocrConfig($cfg['ocr'] ?? []),
            'pdfOptions' => $this->pdfConfig($cfg),
            'images' => ($cfg['extract_images'] ?? false) ? ImageExtractionConfig::default() : null,
        ]);
    }

    /**
     * @deprecated use config()
     */
    public function defaultConfig(): ExtractionConfig
    {
        return $this->config();
    }

    private function ocrConfig(array $ocr): OcrConfig
    {
        $language = $ocr['language'] ?? 'eng';

        $vars = [
            ...get_object_vars(OcrConfig::default()),
            'enabled' => (bool) ($ocr['enabled'] ?? true),
            'backend' => $ocr['backend'] ?? 'tesseract',
            'language' => is_array($language) ? $language : explode('+', $language),
        ];

        if (isset($ocr['auto_rotate'])) {
            $vars['autoRotate'] = (bool) $ocr['auto_rotate'];
        }

        // ponytail: only the on_low_quality policy is constructible from the
        // stubs; add 'always' when the extension exposes a factory for it.
        if (($ocr['vlm_fallback'] ?? 'disabled') === 'on_low_quality') {
            $vars['vlmFallback'] = VlmFallbackPolicy::onLowQuality(
                (float) ($ocr['vlm_quality_threshold'] ?? 0.5)
            );
        }

        if ($llm = $this->llmConfig()) {
            $vars['vlmConfig'] = $llm;
        }

        return new OcrConfig(...$vars);
    }

    private function llmConfig(): ?LlmConfig
    {
        $llm = $this->config['llm'] ?? [];

        if (empty($llm['model'])) {
            return null;
        }

        return new LlmConfig(
            model: $llm['model'],
            apiKey: $llm['api_key'] ?? null,
            baseUrl: $llm['base_url'] ?? null,
        );
    }

    private function pdfConfig(array $cfg): PdfConfig
    {
        return new PdfConfig(...[
            ...get_object_vars(PdfConfig::default()),
            'extractTables' => (bool) ($cfg['extract_tables'] ?? true),
            'extractImages' => (bool) ($cfg['extract_images'] ?? false),
        ]);
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

        return XbergCore::$method(...$arguments);
    }
}
