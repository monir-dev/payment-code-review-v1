<?php

namespace App\Tests\Controller;

use App\Entity\PaymentTransaction;
use App\Repository\PaymentTransactionRepository;
use App\Repository\PlanRepository;
use App\Service\NmiPaymentGateway;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PaymentControllerTest extends WebTestCase
{
    private $paymentGatewayMock;
    private $planRepositoryMock;
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        // Mock NmiPaymentGateway
        $this->paymentGatewayMock = $this->createMock(NmiPaymentGateway::class);
        static::getContainer()->set(NmiPaymentGateway::class, $this->paymentGatewayMock);

        // Mock PlanRepository to return empty plans for forms
        $this->planRepositoryMock = $this->createMock(PlanRepository::class);
        $this->planRepositoryMock->method('findActivePlans')->willReturn([]);
        static::getContainer()->set(PlanRepository::class, $this->planRepositoryMock);
    }

    function testCheckoutPageLoads(): void
    {
        $this->client->request('GET', '/checkout');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Process Payment');
    }

    function testCheckoutWithTokenSuccess(): void
    {
        $this->paymentGatewayMock
            ->method('completeTransaction')
            ->willReturn(['status' => 'success', 'transaction_id' => 'txn_123']);

        $this->client->request('GET', '/checkout?token-id=some-token');
        $this->assertResponseRedirects('/checkout');
        
        // Check that the flash message was set
        $session = $this->client->getRequest()->getSession();
        $flashBag = $session->getFlashBag();
        $successMessages = $flashBag->get('success');
        
        $this->assertNotEmpty($successMessages);
        $this->assertStringContainsString('Payment successful! Transaction ID: txn_123', $successMessages[0]);
    }

    function testCheckoutWithTokenDeclined(): void
    {
        $this->paymentGatewayMock
            ->method('completeTransaction')
            ->willReturn(['status' => 'declined', 'decline_message' => 'Card declined']);

        $this->client->request('GET', '/checkout?token-id=some-token');
        $this->assertResponseRedirects('/checkout');
        
        // Check that the flash message was set
        $session = $this->client->getRequest()->getSession();
        $flashBag = $session->getFlashBag();
        $dangerMessages = $flashBag->get('danger');
        
        $this->assertNotEmpty($dangerMessages);
        $this->assertStringContainsString('Payment declined: Card declined', $dangerMessages[0]);
    }

    function testCheckoutWithTokenError(): void
    {
        $this->paymentGatewayMock
            ->method('completeTransaction')
            ->willReturn(['status' => 'error', 'error_message' => 'Gateway error']);

        $this->client->request('GET', '/checkout?token-id=some-token');
        $this->assertResponseRedirects('/checkout');
        
        // Check that the flash message was set
        $session = $this->client->getRequest()->getSession();
        $flashBag = $session->getFlashBag();
        $dangerMessages = $flashBag->get('danger');
        
        $this->assertNotEmpty($dangerMessages);
        $this->assertStringContainsString('Payment failed: Gateway error', $dangerMessages[0]);
    }

    function testRefundPageLoads(): void
    {
        $this->client->request('GET', '/refund');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Process Refund');
    }

    function testRefundFormSuccess(): void
    {
        $this->paymentGatewayMock
            ->method('processRefund')
            ->willReturn(['status' => 'success', 'transaction_id' => 'refund_txn_123']);

        $crawler = $this->client->request('GET', '/refund');
        $form = $crawler->selectButton('Process Refund')->form([
            'refund[transactionId]' => 'txn_123_original',
            'refund[refundAmount]' => '10.00',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/refund');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-success');
        $this->assertSelectorTextContains('.alert-success', 'Refund successful! New Transaction ID:');
    }

    function testGetTransactions(): void
    {
        $transaction = new PaymentTransaction();
        $transaction->setUuid('some-uuid');
        $transaction->setTransactionId('txn_123');
        $transaction->setAmount(100.50);
        $transaction->setCurrencyCode('USD');
        $transaction->setPaymentStatus('completed');
        $transaction->setLast4Digits('1234');
        $transaction->setCreatedAt(new \DateTime('2023-01-01 12:00:00'));

        $repositoryMock = $this->createMock(PaymentTransactionRepository::class);
        $repositoryMock->method('by')->willReturn([$transaction]);
        static::getContainer()->set(PaymentTransactionRepository::class, $repositoryMock);

        $this->client->request('GET', '/api/transactions');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseContent = $this->client->getResponse()->getContent();
        $data = json_decode($responseContent, true);

        $this->assertCount(1, $data);
        $this->assertEquals([
            'uuid' => 'some-uuid',
            'transaction_id' => 'txn_123',
            'amount' => 100.50,
            'currency_code' => 'USD',
            'payment_status' => 'completed',
            'last4_digits' => '1234',
            'created_at' => '2023-01-01 12:00:00',
        ], $data[0]);
    }

    function testGetTransactionsEmpty(): void
    {
        $repositoryMock = $this->createMock(PaymentTransactionRepository::class);
        $repositoryMock->method('by')->willReturn([]);
        static::getContainer()->set(PaymentTransactionRepository::class, $repositoryMock);

        $this->client->request('GET', '/api/transactions');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $this->assertSame('[]', $this->client->getResponse()->getContent());
    }
}
