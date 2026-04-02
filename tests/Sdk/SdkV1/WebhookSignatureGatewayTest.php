<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Tests\Sdk\SdkV1;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Sdk\SdkV1\WebhookSignatureGateway;
use PostFinanceCheckout\Sdk\Service\WebhookEncryptionService as SdkWebhookEncryptionService;

class WebhookSignatureGatewayTest extends TestCase
{
    private MockObject|SdkWebhookEncryptionService $encryptionService;
    private WebhookSignatureGateway $gateway;
    private MockObject|LoggerInterface $logger;
    private MockObject|SdkProvider $sdkProvider;

    protected function setUp(): void
    {
        $this->sdkProvider = $this->createMock(SdkProvider::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->encryptionService = $this->createMock(SdkWebhookEncryptionService::class);

        $this->sdkProvider->method('getService')
            ->with(SdkWebhookEncryptionService::class)
            ->willReturn($this->encryptionService);

        $this->gateway = new WebhookSignatureGateway($this->sdkProvider, $this->logger);
    }

    public function testValidateReturnsFalseForInvalidSignature(): void
    {
        $header = 'invalid-sig';
        $payload = 'data';

        $this->encryptionService->expects($this->once())
            ->method('isContentValid')
            ->with($header, $payload)
            ->willThrowException(new \Exception("Invalid signature"));

        $this->assertFalse($this->gateway->validate($header, $payload));
    }

    public function testValidateReturnsTrueForValidSignature(): void
    {
        $header = 'valid-sig';
        $payload = 'data';

        $this->encryptionService->expects($this->once())
            ->method('isContentValid')
            ->with($header, $payload)
            ->willReturn(true);

        $this->assertTrue($this->gateway->validate($header, $payload));
    }
}
