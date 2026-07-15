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
