# ums-lspl/photosign-verification-client

Laravel package for **consumer applications** (SmartExam / UMS) that validate passport photos and handwritten signatures via [PhotoSign Verification](https://github.com/rajkumarchanda/photosign).

> Images are checked in memory on PhotoSign and **never stored**. Do not put OpenCV in Laravel.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12

## Installation

### From this monorepo (path)

In your consumer app's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/ums/photosign-verification-client",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "ums-lspl/photosign-verification-client": "@dev"
    }
}
```

```bash
composer require ums-lspl/photosign-verification-client:@dev
php artisan vendor:publish --tag=photosign-config
```

### From Packagist / VCS (when published)

```bash
composer require ums-lspl/photosign-verification-client
php artisan vendor:publish --tag=photosign-config
```

## Configuration

`.env` on your consumer app:

```env
PHOTOSIGN_URL=https://photosign.example.com
PHOTOSIGN_KEY=ps_live_....
PHOTOSIGN_PHOTO_PROFILE=passport_student
PHOTOSIGN_SIGN_PROFILE=signature_student
PHOTOSIGN_TIMEOUT=8
PHOTOSIGN_RETRIES=1
PHOTOSIGN_FAIL_MODE=shadow
PHOTOSIGN_SHADOW=false
PHOTOSIGN_WEBHOOK_SECRET=
```

Create an API key in **PhotoSign → API keys** (assign to the university client). Start with `PHOTOSIGN_FAIL_MODE=shadow` so enrolment is not blocked while you tune thresholds.

`fail_mode`:

| Mode | Behaviour |
|------|-----------|
| `closed` | Validation failures and outages block the upload |
| `shadow` | Call PhotoSign, log would-be rejects, never block the user |
| `open` | Never block; still call PhotoSign when possible |

`PHOTOSIGN_SHADOW=true` is an alias for shadow mode.

## Usage

```php
use PhotoSign\Facades\PhotoSign;
use PhotoSign\Exceptions\ValidationFailedException;
use PhotoSign\Exceptions\PhotoSignUnavailableException;

try {
    PhotoSign::validatePhoto($this->photo, [
        'profile' => config('photosign.photo_profile'),
        'reference_id' => auth()->user()->STUDENTCODE,
    ]);
} catch (ValidationFailedException $e) {
    $this->addError('photo', $e->getMessage());
    foreach ($e->issues() as $issue) {
        // $issue->code, $issue->message, $issue->hint
    }
    return;
} catch (PhotoSignUnavailableException $e) {
    $this->addError('photo', 'Photo validation is temporarily unavailable.');
    return;
}
```

Livewire temporary uploads on **S3/GCS** are supported: the client prefers `readStream()` / `get()` when the SplFileInfo path is not a real local file. You can pass `$this->photo` directly — no app-side materialize helper is required.

Capture widget `dataUrl` values are also accepted by `validatePhoto()`. Decode for storage with:

```php
PhotoSign::validatePhoto($dataUrl, [
    'reference_id' => auth()->user()->STUDENTCODE,
]);
$decoded = PhotoSign::decodeDataUrl($dataUrl);
Storage::disk('s3')->put("profile/{$code}.{$decoded['extension']}", $decoded['contents']);
```

```php
PhotoSign::validateSignature($this->signature, [
    'reference_id' => auth()->user()->STUDENTCODE.'-sign',
]);
```

Batch and signature enrolment:

```php
PhotoSign::enrollSignature($enrolmentSignature, $studentCode);
PhotoSign::validateSignature($examSignature, ['reference_id' => $studentCode]);
$batch = PhotoSign::validateBatch([
    ['file' => $photo, 'kind' => 'photo'],
    ['file' => $signature, 'kind' => 'signature', 'reference_id' => $studentCode],
]);
```

### Webhooks

HMAC header: `X-PhotoSign-Signature: t=<unix>,v1=<hex>` over `{timestamp}.{rawBody}`. Also sent as `X-PhotoSign-Timestamp`. Reject signatures older than ~5 minutes (replay protection). Secrets are shown once in PhotoSign Settings and encrypted at rest:

```php
$raw = $request->getContent();
$ok = PhotoSign::verifyWebhookSignature(
    config('photosign.webhook_secret'),
    $raw,
    (string) $request->header('X-PhotoSign-Signature'),
);
```

### Live capture widget

Do not put API keys in the browser. Your server creates a short-lived session:

```php
$session = PhotoSign::createCaptureSession([
    'kind' => 'photo',
    'reference_id' => auth()->user()->STUDENTCODE,
    'allowed_origin' => config('app.url'),
]);
```

```html
<div id="photosign"></div>
<script src="{{ $session['loader_url'] }}"></script>
<script>
  PhotoSignWidget.mount("#photosign", {
    embedUrl: @json($session['embed_url']),
    onComplete({ items }) {
      const photo = items.find((item) => item.kind === "photo");
      if (!photo?.result.ok) return;
      // POST photo.dataUrl to your Laravel route, then store on S3
    },
  });
</script>
```

## SmartExam integration point

Call PhotoSign in `PhotoSignature::updatedPhoto()` / `updatedSignature()` (and profile media flows) **before** uploading to S3:

1. Validate mime/size locally (existing rules).
2. `PhotoSign::validatePhoto(...)` / `validateSignature(...)`.
3. If `ValidationFailedException`, show `$e->getMessage()` and do not upload.
4. Prefer shadow mode for the first rollout.

## Testing

```bash
cd packages/ums/photosign-verification-client
composer install
./vendor/bin/phpunit
```

## License

MIT
