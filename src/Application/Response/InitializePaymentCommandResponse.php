<?php

declare(strict_types=1);

namespace App\Application\Response;

final class InitializePaymentCommandResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $tokenId = null,
        public readonly ?string $message = null,
        public readonly ?array $gatewayResponse = null,
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

    public function hasRedirectUrl(): bool
    {
        return !empty($this->redirectUrl);
    }

    public function hasTokenId(): bool
    {
        return !empty($this->tokenId);
    }
}
