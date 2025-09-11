<?php

declare(strict_types=1);

namespace App\Application\Response\Gateway;

final class InitializePaymentResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $formUrl = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $tokenId = null,
        public readonly ?string $message = null,
        public readonly ?array $rawResponse = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status !== 'success';
    }

    public function hasFormUrl(): bool
    {
        return !empty($this->formUrl);
    }

    public function hasRedirectUrl(): bool
    {
        return !empty($this->redirectUrl);
    }
}
