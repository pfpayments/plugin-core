<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Tests\Sdk\WebServiceAPIV1;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\ManualTask\Exception\ManualTaskException;
use PostFinanceCheckout\PluginCore\ManualTask\State;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV1\ManualTaskGateway;
use PostFinanceCheckout\Sdk\Model\CriteriaOperator as SdkCriteriaOperator;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilter as SdkEntityQueryFilter;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilterType as SdkEntityQueryFilterType;
use PostFinanceCheckout\Sdk\Service\ManualTaskService as SdkManualTaskService;

class ManualTaskGatewayTest extends TestCase
{
    private ManualTaskGateway $gateway;
    private MockObject|LoggerInterface $logger;
    private MockObject|SdkManualTaskService $manualTaskService;
    private MockObject|SdkProvider $sdkProvider;

    protected function setUp(): void
    {
        $this->sdkProvider = $this->createMock(SdkProvider::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->manualTaskService = $this->createMock(SdkManualTaskService::class);

        $this->sdkProvider->method('getService')
            ->with(SdkManualTaskService::class)
            ->willReturn($this->manualTaskService);

        $this->gateway = new ManualTaskGateway(
            $this->sdkProvider,
            $this->logger,
        );
    }

    public function testCountByStateBuildsAnEqualsFilterOnStateAndReturnsTheCount(): void
    {
        $spaceId = 42;

        $this->manualTaskService->expects($this->once())
            ->method('count')
            ->with(
                $spaceId,
                $this->callback(function (SdkEntityQueryFilter $filter) {
                    return $filter->getType() === SdkEntityQueryFilterType::LEAF
                        && $filter->getOperator() === SdkCriteriaOperator::EQUALS
                        && $filter->getFieldName() === 'state'
                        && $filter->getValue() === State::OPEN->value;
                }),
            )
            ->willReturn(7);

        $this->assertSame(7, $this->gateway->countByState($spaceId, State::OPEN));
    }

    public function testCountByStateWrapsSdkFailuresInManualTaskException(): void
    {
        $spaceId = 42;

        $this->manualTaskService->expects($this->once())
            ->method('count')
            ->willThrowException(new \Exception('SDK unavailable'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to count manual tasks from SDK.'));

        $this->expectException(ManualTaskException::class);
        $this->gateway->countByState($spaceId, State::OPEN);
    }
}
