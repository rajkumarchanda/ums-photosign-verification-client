<?php

namespace PhotoSign;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use PhotoSign\DTO\ValidationResult;
use PhotoSign\Exceptions\PhotoSignUnavailableException;
use PhotoSign\Exceptions\ValidationFailedException;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileInfo;

class Client
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $photoProfile = 'passport_student',
        private readonly string $signatureProfile = 'signature_student',
        private readonly float $timeout = 8.0,
        private readonly int $retries = 1,
        private readonly string $failMode = 'closed',
        private readonly ?LoggerInterface $logger = null,
        private readonly ?HttpClient $http = null,
    ) {
    }

    public static function fromConfig(array $config, ?LoggerInterface $logger = null): self
    {
        $failMode = $config['fail_mode'] ?? 'closed';
        if (!empty($config['shadow'])) {
            $failMode = 'shadow';
        }

        return new self(
            rtrim((string) ($config['url'] ?? ''), '/'),
            (string) ($config['key'] ?? ''),
            (string) ($config['photo_profile'] ?? 'passport_student'),
            (string) ($config['sign_profile'] ?? 'signature_student'),
            (float) ($config['timeout'] ?? 8),
            (int) ($config['retries'] ?? 1),
            $failMode,
            $logger,
        );
    }

    public function validatePhoto(mixed $file, array $options = []): ValidationResult
    {
        return $this->validate('photo', $file, $options['profile'] ?? $this->photoProfile, $options['reference_id'] ?? null);
    }

    public function validateSignature(mixed $file, array $options = []): ValidationResult
    {
        return $this->validate(
            'signature',
            $file,
            $options['profile'] ?? $this->signatureProfile,
            $options['reference_id'] ?? null,
            !empty($options['enroll_reference']),
        );
    }

    /**
     * @param list<array{file:mixed,kind?:string,profile?:string,reference_id?:string}> $items
     * @return list<ValidationResult>
     */
    public function validateBatch(array $items): array
    {
        if ($this->apiKey === '' || $this->baseUrl === '') {
            throw new PhotoSignUnavailableException('PhotoSign URL or API key is not configured.');
        }
        $multipart = [];
        $kinds = [];
        $profiles = [];
        $refs = [];
        foreach ($items as $index => $item) {
            [$contents, $filename] = $this->readFile($item['file']);
            $multipart[] = ['name' => 'files', 'contents' => $contents, 'filename' => $filename ?: 'file-'.$index.'.bin'];
            $kinds[] = $item['kind'] ?? 'photo';
            $profiles[] = $item['profile'] ?? '';
            $refs[] = $item['reference_id'] ?? '';
        }
        $multipart[] = ['name' => 'kinds', 'contents' => implode(',', $kinds)];
        $multipart[] = ['name' => 'profiles', 'contents' => implode(',', $profiles)];
        $multipart[] = ['name' => 'reference_ids', 'contents' => implode(',', $refs)];
        try {
            $response = $this->http()->post($this->baseUrl.'/v1/validate/batch', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/json',
                ],
                'multipart' => $multipart,
                'http_errors' => false,
                'timeout' => max($this->timeout, 20),
            ]);
        } catch (GuzzleException $e) {
            throw new PhotoSignUnavailableException($e->getMessage(), 0, $e);
        }
        $body = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() >= 400 || !is_array($body)) {
            throw new PhotoSignUnavailableException('PhotoSign batch request failed.');
        }
        $results = [];
        foreach ($body['results'] ?? [] as $row) {
            $results[] = $this->finalize($row['version'] ?? 'batch', ValidationResult::fromArray($row), throwOnFail: false);
        }
        return $results;
    }

    public static function verifyWebhookSignature(
        string $secret,
        string $payload,
        string $signature,
        ?int $toleranceSeconds = 300,
        ?int $now = null,
    ): bool {
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            $parts[trim($key)] = trim($value);
        }
        if (!isset($parts['t'], $parts['v1'])) {
            return false;
        }
        if (!ctype_digit($parts['t'])) {
            return false;
        }
        $timestamp = (int) $parts['t'];
        $tolerance = $toleranceSeconds ?? 300;
        $current = $now ?? time();
        if (abs($current - $timestamp) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $parts['v1']);
    }

    public function enrollSignature(mixed $file, string $referenceId, array $options = []): void
    {
        $this->validate('signature', $file, $options['profile'] ?? $this->signatureProfile, $referenceId, true);
    }

    /**
     * @param array{kind?:string,photo_profile?:string,signature_profile?:string,reference_id?:string,allowed_origin?:string,ttl_minutes?:int,max_attempts?:int} $options
     * @return array{session_id:string,token:string,kind:string,expires_at:string,embed_url:string,loader_url:string,allowed_origin:string,max_attempts:int}
     */
    public function createCaptureSession(array $options = []): array
    {
        if ($this->apiKey === '' || $this->baseUrl === '') {
            throw new PhotoSignUnavailableException('PhotoSign URL or API key is not configured.');
        }
        try {
            $response = $this->http()->post($this->baseUrl.'/v1/capture/sessions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'kind' => $options['kind'] ?? 'photo',
                    'photo_profile' => $options['photo_profile'] ?? $this->photoProfile,
                    'signature_profile' => $options['signature_profile'] ?? $this->signatureProfile,
                    'reference_id' => $options['reference_id'] ?? null,
                    'allowed_origin' => $options['allowed_origin'] ?? null,
                    'ttl_minutes' => $options['ttl_minutes'] ?? null,
                    'max_attempts' => $options['max_attempts'] ?? 8,
                ],
                'http_errors' => false,
                'timeout' => $this->timeout,
            ]);
        } catch (GuzzleException $e) {
            throw new PhotoSignUnavailableException($e->getMessage(), 0, $e);
        }
        $body = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() >= 400 || !is_array($body) || empty($body['embed_url'])) {
            $detail = is_string($body['detail'] ?? null) ? $body['detail'] : 'Could not create capture session.';
            throw new PhotoSignUnavailableException($detail);
        }
        return $body;
    }

    private function validate(string $kind, mixed $file, ?string $profile, ?string $referenceId, bool $enroll = false): ValidationResult
    {
        $attempts = max(1, $this->retries + 1);
        $lastError = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $result = $this->request($kind, $file, $profile, $referenceId, $enroll);
                return $this->finalize($kind, $result);
            } catch (PhotoSignUnavailableException $e) {
                $lastError = $e;
                $this->logger()?->warning('PhotoSign unavailable', [
                    'kind' => $kind,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->unavailable($lastError?->getMessage() ?: 'PhotoSign is unavailable.');
    }

    private function request(string $kind, mixed $file, ?string $profile, ?string $referenceId, bool $enroll = false): ValidationResult
    {
        if ($this->apiKey === '' || $this->baseUrl === '') {
            throw new PhotoSignUnavailableException('PhotoSign URL or API key is not configured.');
        }

        [$contents, $filename] = $this->readFile($file);
        $multipart = [
            [
                'name' => 'file',
                'contents' => $contents,
                'filename' => $filename,
            ],
        ];
        if ($profile) {
            $multipart[] = ['name' => 'profile', 'contents' => $profile];
        }
        if ($referenceId) {
            $multipart[] = ['name' => 'reference_id', 'contents' => $referenceId];
        }
        if ($enroll) {
            $multipart[] = ['name' => 'enroll_reference', 'contents' => 'true'];
        }

        try {
            $response = $this->http()->post($this->baseUrl.'/v1/validate/'.$kind, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/json',
                ],
                'multipart' => $multipart,
                'http_errors' => false,
                'timeout' => $this->timeout,
            ]);
        } catch (GuzzleException $e) {
            throw new PhotoSignUnavailableException($e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        if ($status >= 500 || $status === 429) {
            throw new PhotoSignUnavailableException('PhotoSign returned HTTP '.$status);
        }
        if (!is_array($body)) {
            throw new PhotoSignUnavailableException('PhotoSign returned an invalid response.');
        }
        if ($status >= 400) {
            $detail = is_string($body['detail'] ?? null) ? $body['detail'] : 'PhotoSign request failed.';
            throw new PhotoSignUnavailableException($detail);
        }

        return ValidationResult::fromArray($body);
    }

    private function finalize(string $kind, ValidationResult $result, bool $throwOnFail = true): ValidationResult
    {
        $mode = $this->failMode;
        if (in_array($mode, ['shadow', 'open'], true)) {
            if (!$result->wouldOk) {
                $this->logger()?->info('PhotoSign shadow rejection', [
                    'kind' => $kind,
                    'code' => $result->code,
                    'codes' => $result->codes,
                    'reasons' => $result->reasons,
                    'issues' => array_map(static fn ($issue) => $issue->code, $result->issues),
                ]);
            }
            return $result->withOk(true, $mode === 'shadow');
        }
        if (!$result->ok && $throwOnFail) {
            throw new ValidationFailedException($result);
        }
        return $result;
    }

    private function unavailable(string $message): ValidationResult
    {
        $result = ValidationResult::unavailable($message);
        if (in_array($this->failMode, ['shadow', 'open'], true)) {
            $this->logger()?->warning('PhotoSign fail-open after outage', ['error' => $message, 'mode' => $this->failMode]);
            return $result->withOk(true, $this->failMode === 'shadow');
        }
        throw new PhotoSignUnavailableException($message);
    }

    /**
     * @return array{0: string|resource|StreamInterface, 1: string}
     */
    private function readFile(mixed $file): array
    {
        if (is_string($file)) {
            return [fopen($file, 'r') ?: throw new PhotoSignUnavailableException('Unable to read file.'), basename($file)];
        }
        if ($file instanceof SplFileInfo) {
            return [fopen($file->getPathname(), 'r') ?: throw new PhotoSignUnavailableException('Unable to read file.'), $file->getFilename()];
        }
        if (is_object($file) && method_exists($file, 'getRealPath') && method_exists($file, 'getClientOriginalName')) {
            $path = $file->getRealPath();
            if (!$path) {
                throw new PhotoSignUnavailableException('Uploaded file is not available on disk.');
            }
            return [fopen($path, 'r') ?: throw new PhotoSignUnavailableException('Unable to read upload.'), $file->getClientOriginalName()];
        }
        if (is_resource($file)) {
            return [$file, 'upload.bin'];
        }
        throw new PhotoSignUnavailableException('Unsupported file input for PhotoSign.');
    }

    private function http(): HttpClient
    {
        return $this->http ?: new HttpClient();
    }

    private function logger(): ?LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }
}
