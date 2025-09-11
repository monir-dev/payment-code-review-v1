<?php

declare(strict_types=1);

namespace App\Application\Response;

final class TogglePlanStatusCommandResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $planId,
        public readonly string $planName,
        public readonly string $originalStatus,
        public readonly string $newStatus,
        public readonly string $action,
        public readonly string $message,
        public readonly array $events = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function wasActivated(): bool
    {
        return $this->action === 'activated';
    }

    public function wasDeactivated(): bool
    {
        return $this->action === 'deactivated';
    }
}
