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
XBERG_OCR_BACKEND=tesseract   # tesseract | paddleocr | vlm | custom
XBERG_OCR_LANGUAGE=eng        # or e.g. "eng+fra+deu"
XBERG_OCR_AUTO_ROTATE=true
XBERG_EXTRACT_TABLES=true
XBERG_EXTRACT_IMAGES=false

# VLM provider — for XBERG_OCR_BACKEND=vlm or the low-quality fallback
XBERG_LLM_MODEL=              # e.g. openai/gpt-4o-mini, ollama/llama3.2
XBERG_LLM_API_KEY=            # optional; falls back to OPENAI_API_KEY etc.
XBERG_LLM_BASE_URL=           # optional; for self-hosted ollama/vllm/...
XBERG_OCR_VLM_FALLBACK=disabled          # or on_low_quality
XBERG_OCR_VLM_QUALITY_THRESHOLD=0.5
```

## Usage

### Extracting

```php
use Stubbedev\Xberg\Facades\Xberg;

// Just the text — one call
$text = Xberg::text('document.pdf');

// Full result — path or URL, uses the config defaults (tesseract etc.)
$output = Xberg::extract('document.pdf');
$result = $output->getResults()[0];         // \Xberg\ExtractedDocument

echo $result->content;                      // extracted text
echo $result->mimeType;                     // detected format
$result->getMetadata();                     // document metadata
$result->getTables();                       // when extract_tables is on
$result->getImages();                       // when extract_images is on

// Batch — one result per input, same order
$output = Xberg::extractBatch(['a.pdf', 'b.docx', 'scan.png']);
```

### Uploaded files / raw bytes

Uploaded files (and any `SplFileInfo`) are accepted directly:

```php
public function store(Request $request)
{
    return ['text' => Xberg::text($request->file('document'))];
}
```

For raw bytes, build the input yourself:

```php
use Xberg\ExtractInput;

Xberg::extract(ExtractInput::fromBytes($bytes, 'application/pdf', 'report.pdf'));
```

### Per-call config override

`Xberg::config()` builds a native `ExtractionConfig` from `config/xberg.php`; pass overrides with the same array shape:

```php
$output = Xberg::extract('scan.png', Xberg::config([
    'ocr' => ['backend' => 'paddleocr', 'language' => 'eng+deu'],
    'extract_images' => true,
]));
```

Everything not covered by the array keeps the extension's own defaults. Other native xberg options pass through under `native` using xberg's own snake_case schema:

```php
Xberg::config(['native' => ['chunking' => ['max_characters' => 2000]]]);
```

A hand-built `\Xberg\ExtractionConfig` still works anywhere a config is accepted.

### VLM providers

Set `XBERG_LLM_MODEL` and either use the VLM as the OCR backend or as a quality fallback behind tesseract:

```dotenv
# Highest accuracy, slow, per-token cost:
XBERG_OCR_BACKEND=vlm
XBERG_LLM_MODEL=openai/gpt-4o-mini

# Or: tesseract first, VLM only for pages below the quality threshold:
XBERG_OCR_BACKEND=tesseract
XBERG_OCR_VLM_FALLBACK=on_low_quality
XBERG_LLM_MODEL=anthropic/claude-sonnet-4-20250514

# Self-hosted, no API key:
XBERG_LLM_MODEL=ollama/llama3.2
XBERG_LLM_BASE_URL=http://localhost:11434/v1
```

### Everything else on `\Xberg\Xberg`

The facade forwards unknown methods straight through:

```php
Xberg::listSupportedFormats();
Xberg::listOcrBackends();       // ['tesseract', 'paddleocr', ...]
Xberg::mapUrl('https://example.com/docs', $urlConfig);
```

## Testing your app

`Xberg::fake()` swaps in a test double — no native extension needed:

```php
use Stubbedev\Xberg\Facades\Xberg;

public function test_document_upload_extracts_text(): void
{
    $fake = Xberg::fake()->stub('*.pdf', 'Invoice #42 total 100 EUR');

    $this->post('/documents', ['document' => UploadedFile::fake()->create('invoice.pdf')])
        ->assertOk();

    $fake->assertExtracted('invoice.pdf');
}
```

Unstubbed inputs return `"Fake extracted content"`. `assertNothingExtracted()` is there too; registry methods (`list*`, `register*`, ...) become no-ops.

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
