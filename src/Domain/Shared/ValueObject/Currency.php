<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

use InvalidArgumentException;

final class Currency
{
    private const SUPPORTED_CURRENCIES = [
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'CAD' => 'Canadian Dollar',
        'AUD' => 'Australian Dollar',
    ];

    public function __construct(
        private readonly string $code
    ) {
        if (!$this->isSupported($code)) {
            throw new InvalidArgumentException(sprintf('Currency "%s" is not supported', $code));
        }
    }

    public static function fromString(string $code): self
    {
        return new self(strtoupper($code));
    }

    public static function usd(): self
    {
        return new self('USD');
    }

    public static function eur(): self
    {
        return new self('EUR');
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return self::SUPPORTED_CURRENCIES[$this->code];
    }

    public function equals(Currency $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }

    private function isSupported(string $code): bool
    {
        return array_key_exists($code, self::SUPPORTED_CURRENCIES);
    }
}
