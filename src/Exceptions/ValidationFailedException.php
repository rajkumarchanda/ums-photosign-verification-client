<?php

namespace PhotoSign\Exceptions;

use PhotoSign\DTO\ValidationResult;

class ValidationFailedException extends PhotoSignException
{
    public function __construct(public readonly ValidationResult $result)
    {
        parent::__construct($result->primaryReason());
    }

    /** @return list<string> */
    public function codes(): array
    {
        return $this->result->codes ?: ($this->result->code !== 'OK' ? [$this->result->code] : []);
    }

    /** @return list<\PhotoSign\DTO\ValidationIssue> */
    public function issues(): array
    {
        return $this->result->issues;
    }
}
