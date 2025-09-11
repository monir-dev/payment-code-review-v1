<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Shared\ValueObject\Money;

use Symfony\Component\HttpFoundation\RequestStack;

final class PaymentSessionService
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getPaymentSessionData(): array
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        
        if (!$session) {
            return [
                'subscription_data' => null,
                'payment_amount' => null,
            ];
        }

        return [
            'subscription_data' => $session->get('subscription_data'),
            'payment_amount' => $session->get('payment_amount'),
        ];
    }

    public function clearPaymentSessionData(): void
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        
        if ($session) {
            $session->remove('subscription_data');
            $session->remove('payment_amount');
        }
    }

    public function storePaymentSessionData(Money $amount, ?array $subscriptionData = null): void
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        
        if ($session) {
            $session->set('payment_amount', $amount->getAmount());
            
            if ($subscriptionData) {
                $session->set('subscription_data', $subscriptionData);
            } else {
                $session->remove('subscription_data');
            }
        }
    }
}
