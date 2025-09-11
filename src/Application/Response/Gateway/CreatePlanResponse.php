<?php

declare(strict_types=1);

namespace App\Application\Response\Gateway;

final class CreatePlanResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $planId = null,
        public readonly ?string $message = null,
        public readonly ?array $rawResponse = null
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'error' || $this->status === 'failed';
    }
}
