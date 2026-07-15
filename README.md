# laravel-xberg

Laravel wrapper for [xberg-io/xberg](https://github.com/xberg-io/xberg) — document text extraction with OCR (Tesseract, PaddleOCR, Candle), tables, and a full plugin system.

## Requirements

- PHP 8.2+ with the xberg native extension
- Laravel 10, 11 or 12
- Optional: Tesseract OCR / ONNX Runtime, depending on the backends you use

## Installation

xberg is a native PHP extension (composer type `php-ext`), installed with [PIE](https://github.com/php/pie), not `composer require`:

```bash
pie install xberg-io/xberg
composer require stubbedev/laravel-xberg
php artisan vendor:publish --tag=xberg-config
```

The service provider and `Xberg` facade alias are auto-discovered.

## Configuration

`config/xberg.php` (or `.env`):

```dotenv
XBERG_OCR_ENABLED=true
XBERG_OCR_BACKEND=tesseract   # tesseract | paddleocr | candle | custom
XBERG_OCR_LANGUAGE=eng        # or e.g. "eng+fra+deu"
XBERG_EXTRACT_TABLES=true
XBERG_EXTRACT_IMAGES=false
```

## Usage

### Extracting

```php
use Stubbedev\Xberg\Facades\Xberg;

// Path or URL — uses the config defaults (tesseract etc.)
$output = Xberg::extract('document.pdf');
$result = $output->results[0];

echo $result->content;                      // extracted text
echo $result->mimeType;                     // detected format
echo $result->metadata->title;              // document metadata
echo $result->metadata->pdf->page_count;

foreach ($result->tables as $table) {       // when extract_tables is on
    echo $table->markdown;
}

// Batch — one result per input, same order
$output = Xberg::extractBatch(['a.pdf', 'b.docx', 'scan.png']);
```

### Uploaded files / raw bytes

```php
use Xberg\ExtractInput;

public function store(Request $request)
{
    $file = $request->file('document');

    $output = Xberg::extract(ExtractInput::fromBytes(
        $file->getContent(),
        $file->getMimeType(),
        $file->getClientOriginalName(),
    ));

    return ['text' => $output->results[0]->content];
}
```

### Per-call config override

```php
use Xberg\ExtractionConfig;
use Xberg\OcrConfig;

$output = Xberg::extract('scan.png', new ExtractionConfig(
    ocr: new OcrConfig(backend: 'paddleocr', language: 'eng+deu'),
    extractTables: true,
));
```

### Everything else on `\Xberg\Xberg`

The facade forwards unknown methods straight through:

```php
Xberg::listSupportedFormats();
Xberg::listOcrBackends();       // ['tesseract', 'paddleocr', ...]
Xberg::mapUrl('https://example.com/docs', $urlConfig);
```

## Plugins

Implement one of xberg's plugin interfaces (`\Xberg\OcrBackend`, `\Xberg\PostProcessor`, `\Xberg\Validator`, `\Xberg\DocumentExtractor`, `\Xberg\EmbeddingBackend`, `\Xberg\Renderer`, `\Xberg\RerankerBackend`, `\Xberg\TokenizerBackend`) and list the class in `config/xberg.php`. Plugins are resolved through the container (constructor injection works) and registered with xberg once per PHP process on boot.

### Example: post-processor

```php
namespace App\Xberg;

use Xberg\ExtractedDocument;
use Xberg\ExtractionConfig;
use Xberg\PostProcessor;

class PiiScrubber implements PostProcessor
{
    public function __construct(private readonly \App\Services\PiiDetector $detector)
    {
    }

    public function process(ExtractedDocument $result, ExtractionConfig $config): mixed
    {
        $result->content = $this->detector->scrub($result->content);

        return $result;
    }

    public function processing_stage(): mixed
    {
        return 'late'; // early | middle | late
    }
}
```

```php
// config/xberg.php
'plugins' => [
    'post_processors' => [
        App\Xberg\PiiScrubber::class,
    ],
],
```

### Example: custom document extractor

```php
namespace App\Xberg;

use Xberg\DocumentExtractor;
use Xberg\ExtractedDocument;
use Xberg\ExtractInput;
use Xberg\ExtractionConfig;

class JsonLinesExtractor implements DocumentExtractor
{
    public function extract(ExtractInput $input, ExtractionConfig $config): ExtractedDocument
    {
        // parse and return an ExtractedDocument
    }

    public function supported_mime_types(): mixed
    {
        return ['application/jsonl'];
    }
}
```

Optional methods (`priority()`, `should_process()`, `initialize()`, `shutdown()`, ...) are picked up when defined — see the [xberg plugin guide](https://docs.xberg.io/guides/plugins/). Priorities above 50 override the built-in extractor for the same MIME type.

Runtime registration works too:

```php
Xberg::registerPostProcessor(new PiiScrubber($detector));
Xberg::unregisterPostProcessor('pii-scrubber');
```
