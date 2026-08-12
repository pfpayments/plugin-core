<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV1;

use PostFinanceCheckout\PluginCore\Log\DomainLoggerTrait;
use PostFinanceCheckout\PluginCore\Log\LogContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\ManualTask\Exception\ManualTaskException;
use PostFinanceCheckout\PluginCore\ManualTask\ManualTaskGatewayInterface;
use PostFinanceCheckout\PluginCore\ManualTask\State;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\Sdk\Model\CriteriaOperator as SdkCriteriaOperator;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilter as SdkEntityQueryFilter;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilterType as SdkEntityQueryFilterType;
use PostFinanceCheckout\Sdk\Service\ManualTaskService as SdkManualTaskService;

/**
 * Gateway implementation using the SDK.
 */
#[LogContext(domain: 'manual_task')]
class ManualTaskGateway implements ManualTaskGatewayInterface
{
    use DomainLoggerTrait;

    /**
     * @param SdkProvider $sdkProvider The SDK provider.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
    }

    /**
     * @inheritDoc
     */
    public function countAll(int $spaceId): int
    {
        try {
            /** @var SdkManualTaskService $service */
            $service = $this->sdkProvider->getService(SdkManualTaskService::class);

            // No filter: the endpoint then counts every manual task in the space.
            return (int)$service->count($spaceId);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to count manual tasks from SDK.', [
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw SdkProvider::wrapException(
                $e,
                ManualTaskException::class,
                'count',
                ['spaceId' => $spaceId],
                'Failed to retrieve manual tasks. Please try again or contact support.',
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function countByState(int $spaceId, State $state): int
    {
        try {
            /** @var SdkManualTaskService $service */
            $service = $this->sdkProvider->getService(SdkManualTaskService::class);

            $filter = new SdkEntityQueryFilter();
            $filter->setType(SdkEntityQueryFilterType::LEAF);
            $filter->setOperator(SdkCriteriaOperator::EQUALS);
            $filter->setFieldName('state');
            $filter->setValue($state->value);

            return (int)$service->count($spaceId, $filter);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to count manual tasks from SDK.', [
                'spaceId' => $spaceId,
                'state' => $state->value,
                'exception' => $e,
            ]);
            throw SdkProvider::wrapException(
                $e,
                ManualTaskException::class,
                'count',
                ['spaceId' => $spaceId, 'state' => $state->value],
                'Failed to retrieve manual tasks. Please try again or contact support.',
            );
        }
    }

}
