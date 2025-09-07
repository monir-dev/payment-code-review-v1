<?php

namespace App\Tests\Service;

use App\Entity\PaymentTransaction;
use App\Service\NmiPaymentGateway;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use PHPUnit\Framework\MockObject\MockObject;

final class NmiPaymentGatewayTest extends TestCase
{
    private MockObject & EntityManagerInterface $entityManager;
    private MockObject & LoggerInterface $logger;
    private MockObject & HttpClientInterface $httpClient;
    private NmiPaymentGateway $paymentGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->paymentGateway = new NmiPaymentGateway(
            $this->entityManager,
            $this->logger,
            $this->httpClient,
            $_ENV['NMI_API_KEY'] ?? ''
        );
    }

    function test_initialize_payment_success()
    {
        $responseXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response>
    <result>1</result>
    <form-url>https://secure.nmi.com/token-form/12345</form-url>
</response>
XML;

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($responseXml);

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->paymentGateway->initializePayment(10.00, 'USD', 'http://localhost/redirect');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('https://secure.nmi.com/token-form/12345', $result['form_url']);
    }

    function test_initialize_payment_error()
    {
        $responseXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response>
    <result>0</result>
</response>
XML;

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($responseXml);

        $this->httpClient->method('request')->willReturn($response);

        $this->logger->expects($this->once())->method('error')->with('Step 1 failed', ['response' => $responseXml]);

        $result = $this->paymentGateway->initializePayment(10.00, 'USD', 'http://localhost/redirect');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Failed to initialize payment', $result['message']);
    }

    function testCompleteTransactionSuccess()
    {
        $request = new Request([], ['token-id' => 'test-token-id']);
        $responseXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response>
    <result>1</result>
    <token-id>test-token-id</token-id>
    <transaction-id>1234567890</transaction-id>
    <amount>10.00</amount>
    <currency>USD</currency>
    <billing>
        <cc-number>...1111</cc-number>
    </billing>
</response>
XML;

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($responseXml);

        $this->httpClient->method('request')->willReturn($response);

        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(PaymentTransaction::class));
        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->once())->method('info')->with('Payment successful', ['transaction_id' => '1234567890']);

        $result = $this->paymentGateway->completeTransaction($request);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('1234567890', $result['transaction_id']);
    }

    function testCompleteTransactionDeclined()
    {
        $request = new Request([], ['token-id' => 'test-token-id']);
        $responseXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response>
    <result>2</result>
    <result-text>Declined</result-text>
</response>
XML;

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($responseXml);

        $this->httpClient->method('request')->willReturn($response);

        $this->logger->expects($this->once())->method('warning')->with('Payment declined', ['response' => $responseXml]);

        $result = $this->paymentGateway->completeTransaction($request);

        $this->assertEquals('declined', $result['status']);
        $this->assertEquals('Declined', $result['decline_message']);
    }

    function testCompleteTransactionError()
    {
        $request = new Request([], ['token-id' => 'test-token-id']);
        $responseXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response>
    <result>3</result>
    <result-text>Error</result-text>
</response>
XML;

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($responseXml);

        $this->httpClient->method('request')->willReturn($response);

        $this->logger->expects($this->once())->method('error')->with('Payment error', ['response' => $responseXml]);

        $result = $this->paymentGateway->completeTransaction($request);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Error', $result['error_message']);
    }

    function testProcessRefundWithNegativeAmount()
    {
        $result = $this->paymentGateway->processRefund('123', -10);
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Refund amount must be positive.', $result['message']);
    }
}
