<?php

namespace PhotoSign\DTO;

class ValidationResult
{
    /**
     * @param list<string> $codes
     * @param list<ValidationIssue> $issues
     * @param list<string> $reasons
     * @param array<string, mixed> $metrics
     */
    public function __construct(
        public readonly bool $ok,
        public readonly bool $wouldOk,
        public readonly int $score,
        public readonly string $code,
        public readonly array $codes,
        public readonly array $issues,
        public readonly array $reasons,
        public readonly array $metrics,
        public readonly string $version,
        public readonly string $failMode = 'closed',
        public readonly bool $shadow = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $issues = [];
        foreach ($data['issues'] ?? [] as $issue) {
            if (is_array($issue)) {
                $issues[] = ValidationIssue::fromArray($issue);
            }
        }
        $reasons = array_values(array_map('strval', $data['reasons'] ?? []));
        $codes = array_values(array_map('strval', $data['codes'] ?? []));
        if (!$codes && !empty($data['code']) && ($data['code'] ?? 'OK') !== 'OK') {
            $codes = [(string) $data['code']];
        }

        return new self(
            (bool) ($data['ok'] ?? false),
            array_key_exists('would_ok', $data) ? (bool) $data['would_ok'] : (bool) ($data['ok'] ?? false),
            (int) ($data['score'] ?? 0),
            (string) ($data['code'] ?? 'UNKNOWN'),
            $codes,
            $issues,
            $reasons,
            is_array($data['metrics'] ?? null) ? $data['metrics'] : [],
            (string) ($data['version'] ?? ''),
            (string) ($data['fail_mode'] ?? 'closed'),
            (bool) ($data['shadow'] ?? false),
        );
    }

    public function primaryReason(): string
    {
        return $this->reasons[0] ?? ($this->issues[0]->message ?? 'Image did not pass validation.');
    }

    public function withOk(bool $ok, bool $shadow = false): self
    {
        return new self(
            $ok,
            $this->wouldOk,
            $this->score,
            $this->code,
            $this->codes,
            $this->issues,
            $this->reasons,
            $this->metrics,
            $this->version,
            $this->failMode,
            $this->shadow || $shadow,
        );
    }

    public static function unavailable(string $message): self
    {
        return new self(
            false,
            false,
            0,
            'SERVICE_UNAVAILABLE',
            ['SERVICE_UNAVAILABLE'],
            [new ValidationIssue('SERVICE_UNAVAILABLE', $message, 'Try again in a moment or contact support.')],
            [$message],
            [],
            '',
            'closed',
            false,
        );
    }
}
