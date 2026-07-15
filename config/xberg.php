<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default extraction settings
    |--------------------------------------------------------------------------
    |
    | Used to build the default \Xberg\ExtractionConfig for every extract()
    | call that does not pass its own config.
    |
    */

    'extract_tables' => env('XBERG_EXTRACT_TABLES', true),
    'extract_images' => env('XBERG_EXTRACT_IMAGES', false),

    /*
    |--------------------------------------------------------------------------
    | OCR
    |--------------------------------------------------------------------------
    |
    | backend: tesseract | paddleocr | candle | <any registered OcrBackend>
    | language: tesseract language code(s), e.g. "eng" or "eng+fra+deu"
    |
    */

    'ocr' => [
        'enabled' => env('XBERG_OCR_ENABLED', true),
        'backend' => env('XBERG_OCR_BACKEND', 'tesseract'),
        'language' => env('XBERG_OCR_LANGUAGE', 'eng'),
        'auto_rotate' => env('XBERG_OCR_AUTO_ROTATE', true),

        // 'disabled' or 'on_low_quality' — retry pages with the VLM below
        // (requires llm.model) when classical OCR quality drops under the
        // threshold.
        'vlm_fallback' => env('XBERG_OCR_VLM_FALLBACK', 'disabled'),
        'vlm_quality_threshold' => env('XBERG_OCR_VLM_QUALITY_THRESHOLD', 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM / VLM provider
    |--------------------------------------------------------------------------
    |
    | Used when ocr.backend is "vlm" or ocr.vlm_fallback is enabled.
    | model uses liter-llm routing: "openai/gpt-4o-mini",
    | "anthropic/claude-sonnet-4-20250514", "ollama/llama3.2", ...
    | api_key may be null — xberg falls back to the provider's standard
    | env var (OPENAI_API_KEY, ANTHROPIC_API_KEY, ...).
    | base_url overrides the endpoint for self-hosted engines
    | (ollama/lmstudio/vllm/llamacpp).
    |
    */

    'llm' => [
        'model' => env('XBERG_LLM_MODEL'),
        'api_key' => env('XBERG_LLM_API_KEY'),
        'base_url' => env('XBERG_LLM_BASE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    |
    | Class names implementing the matching Xberg interface. Each is resolved
    | through the container and registered with xberg on boot, so plugins may
    | use constructor injection.
    |
    | 'ocr_backends'        => \Xberg\OcrBackend
    | 'post_processors'     => \Xberg\PostProcessor
    | 'validators'          => \Xberg\Validator
    | 'document_extractors' => \Xberg\DocumentExtractor
    | 'embedding_backends'  => \Xberg\EmbeddingBackend
    | 'renderers'           => \Xberg\Renderer
    | 'reranker_backends'   => \Xberg\RerankerBackend
    | 'tokenizer_backends'  => \Xberg\TokenizerBackend
    |
    */

    'plugins' => [
        'ocr_backends' => [],
        'post_processors' => [],
        'validators' => [],
        'document_extractors' => [],
        'embedding_backends' => [],
        'renderers' => [],
        'reranker_backends' => [],
        'tokenizer_backends' => [],
    ],

];
