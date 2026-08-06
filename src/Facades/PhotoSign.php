<?php

namespace PhotoSign\Facades;

use Illuminate\Support\Facades\Facade;
use PhotoSign\DTO\ValidationResult;

/**
 * @method static ValidationResult validatePhoto(mixed $file, array $options = [])
 * @method static ValidationResult validateSignature(mixed $file, array $options = [])
 * @method static list<ValidationResult> validateBatch(array $items)
 * @method static void enrollSignature(mixed $file, string $referenceId, array $options = [])
 * @method static array createCaptureSession(array $options = [])
 * @method static array{contents: string, extension: string, filename: string, mime: string} decodeDataUrl(string $dataUrl)
 * @method static bool verifyWebhookSignature(string $secret, string $payload, string $signature, ?int $toleranceSeconds = 300)
 */
class PhotoSign extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PhotoSign\PhotoSign::class;
    }
}
