<?php

namespace PhotoSign\DTO;

class ValidationIssue
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly string $hint = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['code'] ?? 'UNKNOWN'),
            (string) ($data['message'] ?? ''),
            (string) ($data['hint'] ?? ''),
        );
    }
}
