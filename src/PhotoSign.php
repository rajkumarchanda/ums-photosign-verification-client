<?php

namespace PhotoSign;

use PhotoSign\DTO\ValidationResult;

class PhotoSign
{
    public function __construct(private readonly Client $client)
    {
    }

    public function validatePhoto(mixed $file, array $options = []): ValidationResult
    {
        return $this->client->validatePhoto($file, $options);
    }

    public function validateSignature(mixed $file, array $options = []): ValidationResult
    {
        return $this->client->validateSignature($file, $options);
    }

    /** @param list<array{file:mixed,kind?:string,profile?:string,reference_id?:string}> $items */
    public function validateBatch(array $items): array
    {
        return $this->client->validateBatch($items);
    }

    public function enrollSignature(mixed $file, string $referenceId, array $options = []): void
    {
        $this->client->enrollSignature($file, $referenceId, $options);
    }

    /** @param array{kind?:string,photo_profile?:string,signature_profile?:string,reference_id?:string,allowed_origin?:string,ttl_minutes?:int,max_attempts?:int} $options */
    public function createCaptureSession(array $options = []): array
    {
        return $this->client->createCaptureSession($options);
    }

    public function verifyWebhookSignature(
        string $secret,
        string $payload,
        string $signature,
        ?int $toleranceSeconds = 300,
    ): bool {
        return Client::verifyWebhookSignature($secret, $payload, $signature, $toleranceSeconds);
    }
}
