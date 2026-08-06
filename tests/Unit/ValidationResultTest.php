<?php

namespace PhotoSign\Tests\Unit;

use PhotoSign\Client;
use PhotoSign\DTO\ValidationIssue;
use PhotoSign\DTO\ValidationResult;
use PhotoSign\Exceptions\ValidationFailedException;
use PhotoSign\Tests\TestCase;

class ValidationResultTest extends TestCase
{
    public function test_from_array_parses_issues_and_codes(): void
    {
        $result = ValidationResult::fromArray([
            'ok' => false,
            'would_ok' => false,
            'score' => 64,
            'code' => 'FACE_NOT_CENTERED',
            'codes' => ['FACE_NOT_CENTERED', 'TOO_DARK'],
            'issues' => [
                [
                    'code' => 'FACE_NOT_CENTERED',
                    'message' => 'Your face is not centered in the frame.',
                    'hint' => 'Move so your face sits in the middle.',
                ],
            ],
            'reasons' => ['Your face is not centered in the frame.'],
            'metrics' => ['face_count' => 1],
            'version' => 'photo-yunet-1.2.0',
            'fail_mode' => 'closed',
            'shadow' => false,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame(['FACE_NOT_CENTERED', 'TOO_DARK'], $result->codes);
        $this->assertCount(1, $result->issues);
        $this->assertInstanceOf(ValidationIssue::class, $result->issues[0]);
        $this->assertSame('FACE_NOT_CENTERED', $result->issues[0]->code);
        $this->assertSame('Your face is not centered in the frame.', $result->primaryReason());
    }

    public function test_validation_failed_exception_exposes_codes(): void
    {
        $result = ValidationResult::fromArray([
            'ok' => false,
            'code' => 'NO_FACE',
            'codes' => ['NO_FACE'],
            'issues' => [['code' => 'NO_FACE', 'message' => 'No face', 'hint' => 'Retake']],
            'reasons' => ['No face'],
            'metrics' => [],
            'version' => 'photo-yunet-1.2.0',
        ]);
        $exception = new ValidationFailedException($result);
        $this->assertSame('No face', $exception->getMessage());
        $this->assertSame(['NO_FACE'], $exception->codes());
    }

    public function test_webhook_signature_verification(): void
    {
        $payload = '{"event":"validation.completed"}';
        $secret = 'whsec_test';
        $ts = 1700000000;
        $sig = 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$payload, $secret);
        $this->assertTrue(Client::verifyWebhookSignature($secret, $payload, $sig, 300, $ts));
        $this->assertFalse(Client::verifyWebhookSignature($secret, $payload, $sig, 300, $ts + 301));
        $this->assertFalse(Client::verifyWebhookSignature($secret, $payload, 'bad', 300, $ts));
    }
}
