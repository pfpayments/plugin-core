<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Tests\Sdk\WebServiceAPIV1;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV1\TransactionCompletionGateway;
use PostFinanceCheckout\PluginCore\Transaction\Completion\State;
use PostFinanceCheckout\PluginCore\Transaction\Completion\TransactionCompletion;
use PostFinanceCheckout\PluginCore\Transaction\Void\State as VoidState;
use PostFinanceCheckout\PluginCore\Transaction\Void\TransactionVoid;
use PostFinanceCheckout\Sdk\Model\FailureReason as SdkFailureReason;
use PostFinanceCheckout\Sdk\Model\TransactionCompletion as SdkTransactionCompletion;
use PostFinanceCheckout\Sdk\Model\TransactionCompletionState;
use PostFinanceCheckout\Sdk\Model\TransactionVoid as SdkTransactionVoid;
use PostFinanceCheckout\Sdk\Model\TransactionVoidState;
use PostFinanceCheckout\Sdk\Service\TransactionCompletionService as SdkTransactionCompletionService;
use PostFinanceCheckout\Sdk\Service\TransactionVoidService as SdkTransactionVoidService;

class TransactionCompletionGatewayTest extends TestCase
{
    private MockObject|SdkTransactionCompletionService $completionService;
    private TransactionCompletionGateway $gateway;
    private MockObject|SdkProvider $sdkProvider;
    private MockObject|SdkTransactionVoidService $voidService;

    protected function setUp(): void
    {
        $this->sdkProvider = $this->createMock(SdkProvider::class);
        $this->completionService = $this->createMock(SdkTransactionCompletionService::class);
        $this->voidService = $this->createMock(SdkTransactionVoidService::class);

        $this->sdkProvider->method('getService')
            ->willReturnMap([
                [SdkTransactionCompletionService::class, $this->completionService],
                [SdkTransactionVoidService::class, $this->voidService],
            ]);

        $this->gateway = new TransactionCompletionGateway($this->sdkProvider);
    }

    public function testCaptureMapsFailureReason(): void
    {
        $spaceId = 1;
        $transactionId = 2;

        $failureReason = new SdkFailureReason();
        $failureReason->setDescription(['en-US' => 'Insufficient funds']);

        $sdkCompletion = new SdkTransactionCompletion();
        $sdkCompletion->setId(10);
        $sdkCompletion->setLinkedTransaction($transactionId);
        $sdkCompletion->setState(TransactionCompletionState::FAILED);
        $sdkCompletion->setFailureReason($failureReason);

        $this->completionService->expects($this->once())
            ->method('completeOnline')
            ->with($spaceId, $transactionId)
            ->willReturn($sdkCompletion);

        $result = $this->gateway->capture($spaceId, $transactionId);

        $this->assertNotNull($result->failureReason);
        $this->assertEquals('Insufficient funds', $result->failureReason->localize('en-US'));
    }

    public function testCaptureReturnsCompletion(): void
    {
        $spaceId = 1;
        $transactionId = 2;

        $sdkCompletion = new SdkTransactionCompletion();
        $sdkCompletion->setId(10);
        $sdkCompletion->setLinkedTransaction($transactionId);
        $sdkCompletion->setState(TransactionCompletionState::SUCCESSFUL);

        $this->completionService->expects($this->once())
            ->method('completeOnline')
            ->with($spaceId, $transactionId)
            ->willReturn($sdkCompletion);

        $result = $this->gateway->capture($spaceId, $transactionId);

        $this->assertInstanceOf(TransactionCompletion::class, $result);
        $this->assertEquals(10, $result->id);
        $this->assertEquals($transactionId, $result->linkedTransactionId);
        $this->assertEquals(State::SUCCESSFUL, $result->state);
        $this->assertNull($result->failureReason);
    }

    public function testVoidMapsFailureReason(): void
    {
        $spaceId = 1;
        $transactionId = 2;

        $failureReason = new SdkFailureReason();
        $failureReason->setDescription(['en-US' => 'Void rejected by gateway']);

        $sdkVoid = new SdkTransactionVoid();
        $sdkVoid->setState(TransactionVoidState::FAILED);
        $sdkVoid->setFailureReason($failureReason);

        $this->voidService->expects($this->once())
            ->method('voidOnline')
            ->with($spaceId, $transactionId)
            ->willReturn($sdkVoid);

        $result = $this->gateway->void($spaceId, $transactionId);

        $this->assertInstanceOf(TransactionVoid::class, $result);
        $this->assertEquals(VoidState::FAILED, $result->state);
        $this->assertNotNull($result->failureReason);
        $this->assertEquals('Void rejected by gateway', $result->failureReason->localize('en-US'));
    }

    public function testVoidReturnsTransactionVoid(): void
    {
        $spaceId = 1;
        $transactionId = 2;

        $sdkVoid = new SdkTransactionVoid();
        $sdkVoid->setState(TransactionVoidState::SUCCESSFUL);

        $this->voidService->expects($this->once())
            ->method('voidOnline')
            ->with($spaceId, $transactionId)
            ->willReturn($sdkVoid);

        $result = $this->gateway->void($spaceId, $transactionId);

        $this->assertInstanceOf(TransactionVoid::class, $result);
        $this->assertEquals(VoidState::SUCCESSFUL, $result->state);
        $this->assertNull($result->failureReason);
    }
}
