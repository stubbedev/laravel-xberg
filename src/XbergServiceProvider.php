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
        $this->registerAboutCommand();
    }

    private function registerAboutCommand(): void
    {
        if (!class_exists(\Illuminate\Foundation\Console\AboutCommand::class)) {
            return;
        }

        \Illuminate\Foundation\Console\AboutCommand::add('Xberg', fn () => [
            'Extension' => extension_loaded('xberg') ? phpversion('xberg') ?: 'loaded' : '<fg=red>not installed</>',
            'OCR' => config('xberg.ocr.enabled')
                ? config('xberg.ocr.backend').' ('.config('xberg.ocr.language').')'
                : 'disabled',
            'Plugins' => (string) collect(config('xberg.plugins', []))->flatten()->count(),
        ]);
    }

    private function registerPlugins(): void
    {
        if (self::$pluginsRegistered) {
            return;
        }

        $plugins = config('xberg.plugins', []);

        if (!extension_loaded('xberg') && collect($plugins)->flatten()->isNotEmpty()) {
            throw new \RuntimeException(
                'config/xberg.php lists plugins, but the xberg extension is not '.
                'installed. Install it with "pie install xberg-io/xberg".'
            );
        }

        foreach (self::PLUGIN_TYPES as $key => $method) {
            foreach ($plugins[$key] ?? [] as $class) {
                XbergCore::$method($this->app->make($class));
            }
        }

        self::$pluginsRegistered = true;
    }
}
