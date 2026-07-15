<?php

declare(strict_types=1);

namespace Stubbedev\Xberg;

use Illuminate\Support\ServiceProvider;
use Xberg\Xberg as XbergCore;

class XbergServiceProvider extends ServiceProvider
{
    /**
     * Config key => \Xberg\Xberg registration method.
     */
    private const PLUGIN_TYPES = [
        'ocr_backends' => 'registerOcrBackend',
        'post_processors' => 'registerPostProcessor',
        'validators' => 'registerValidator',
        'document_extractors' => 'registerDocumentExtractor',
        'embedding_backends' => 'registerEmbeddingBackend',
        'renderers' => 'registerRenderer',
        'reranker_backends' => 'registerRerankerBackend',
        'tokenizer_backends' => 'registerTokenizerBackend',
    ];

    // ponytail: xberg's plugin registry lives in the native extension and
    // survives for the lifetime of the PHP process (FPM worker / octane),
    // so guard against re-registering on every request.
    private static bool $pluginsRegistered = false;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/xberg.php', 'xberg');

        $this->app->singleton(XbergManager::class, fn ($app) => new XbergManager($app['config']['xberg']));
        $this->app->alias(XbergManager::class, 'xberg');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/xberg.php' => config_path('xberg.php'),
        ], 'xberg-config');

        $this->registerPlugins();
    }

    private function registerPlugins(): void
    {
        if (self::$pluginsRegistered) {
            return;
        }

        $plugins = config('xberg.plugins', []);

        foreach (self::PLUGIN_TYPES as $key => $method) {
            foreach ($plugins[$key] ?? [] as $class) {
                XbergCore::$method($this->app->make($class));
            }
        }

        self::$pluginsRegistered = true;
    }
}
