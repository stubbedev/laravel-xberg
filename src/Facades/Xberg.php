<?php

declare(strict_types=1);

namespace Stubbedev\Xberg\Facades;

use Illuminate\Support\Facades\Facade;
use Stubbedev\Xberg\XbergFake;
use Stubbedev\Xberg\XbergManager;

/**
 * @method static \Xberg\ExtractionResult extract(\Xberg\ExtractInput|string|\SplFileInfo $input, ?\Xberg\ExtractionConfig $config = null)
 * @method static string text(\Xberg\ExtractInput|string|\SplFileInfo $input, ?\Xberg\ExtractionConfig $config = null)
 * @method static \Xberg\ExtractionResult extractBatch(array $inputs, ?\Xberg\ExtractionConfig $config = null)
 * @method static \Xberg\ExtractionConfig config(array $overrides = [])
 * @method static \Xberg\ExtractionConfig defaultConfig()
 * @method static array listSupportedFormats()
 * @method static array listOcrBackends()
 * @method static array listDocumentExtractors()
 * @method static array listPostProcessors()
 * @method static array listValidators()
 * @method static array listEmbeddingBackends()
 * @method static array listRenderers()
 * @method static array listRerankerBackends()
 * @method static array listTokenizerBackends()
 * @method static void registerOcrBackend(\Xberg\OcrBackend $backend)
 * @method static void registerPostProcessor(\Xberg\PostProcessor $backend)
 * @method static void registerValidator(\Xberg\Validator $backend)
 * @method static void registerDocumentExtractor(\Xberg\DocumentExtractor $backend)
 * @method static void registerEmbeddingBackend(\Xberg\EmbeddingBackend $backend)
 * @method static void registerRenderer(\Xberg\Renderer $backend)
 * @method static void registerRerankerBackend(\Xberg\RerankerBackend $backend)
 * @method static void registerTokenizerBackend(\Xberg\TokenizerBackend $backend)
 * @method static void unregisterOcrBackend(string $name)
 * @method static void unregisterPostProcessor(string $name)
 * @method static void unregisterValidator(string $name)
 * @method static void unregisterDocumentExtractor(string $name)
 * @method static void unregisterEmbeddingBackend(string $name)
 * @method static void unregisterRenderer(string $name)
 * @method static void unregisterRerankerBackend(string $name)
 * @method static void unregisterTokenizerBackend(string $name)
 *
 * @see XbergManager
 */
class Xberg extends Facade
{
    /**
     * Replace the bound manager with a fake for testing.
     */
    public static function fake(): XbergFake
    {
        static::swap($fake = new XbergFake());

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return XbergManager::class;
    }
}
