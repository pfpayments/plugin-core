<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV1;

use PostFinanceCheckout\PluginCore\Localization\LocalizedString;
use PostFinanceCheckout\PluginCore\Log\DomainLoggerTrait;
use PostFinanceCheckout\PluginCore\Log\LogContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\PaymentMethod\Exception\PaymentMethodException;
use PostFinanceCheckout\PluginCore\PaymentMethod\PaymentMethod;
use PostFinanceCheckout\PluginCore\PaymentMethod\PaymentMethodCollection;
use PostFinanceCheckout\PluginCore\PaymentMethod\PaymentMethodGatewayInterface;
use PostFinanceCheckout\PluginCore\Sdk\PaymentMethodMapperTrait;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\Sdk\Model\CreationEntityState as SdkCreationEntityState;
use PostFinanceCheckout\Sdk\Model\CriteriaOperator as SdkCriteriaOperator;
use PostFinanceCheckout\Sdk\Model\EntityQuery as SdkEntityQuery;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilter as SdkEntityQueryFilter;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilterType as SdkEntityQueryFilterType;
use PostFinanceCheckout\Sdk\Service\PaymentMethodConfigurationService as SdkPaymentMethodConfigurationService;

/**
 * Gateway implementation using the SDK.
 */
#[LogContext(domain: 'sync')]
class PaymentMethodGateway implements PaymentMethodGatewayInterface
{
    use DomainLoggerTrait;
    use PaymentMethodMapperTrait;

    /**
     * @param SdkProvider $provider The SDK provider.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly SdkProvider $provider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
    }

    /**
     * Helper to create an SDK filter.
     *
     * @param string $fieldName The field to filter on.
     * @param mixed $value The value to filter by.
     * @param string $operator The operator to use (defaults to EQUALS).
     * @return SdkEntityQueryFilter The created filter.
     */
    private function createFilter(string $fieldName, mixed $value, string $operator = SdkCriteriaOperator::EQUALS): SdkEntityQueryFilter
    {
        $filter = new SdkEntityQueryFilter();
        $filter->setType(SdkEntityQueryFilterType::LEAF);
        $filter->setOperator($operator);
        $filter->setFieldName($fieldName);
        $filter->setValue($value);
        return $filter;
    }

    /**
     * @inheritDoc
     */
    public function fetchById(int $spaceId, int $id): PaymentMethod
    {
        try {
            /** @var SdkPaymentMethodConfigurationService $service */
            $service = $this->provider->getService(SdkPaymentMethodConfigurationService::class);

            $config = $service->read($spaceId, $id);

            return $this->mapToPaymentMethod($config);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch payment method from SDK.', [
                'paymentMethodId' => $id,
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw new PaymentMethodException(
                sprintf('Payment method %d not found.', $id),
                new LocalizedString('The payment method could not be retrieved.'),
                $e,
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function fetchBySpaceId(int $spaceId, ?string $state = null): PaymentMethodCollection
    {
        try {
            /** @var SdkPaymentMethodConfigurationService $service */
            $service = $this->provider->getService(SdkPaymentMethodConfigurationService::class);

            $query = new SdkEntityQuery();

            if ($state !== null) {
                $query->setFilter($this->createFilter('state', $state));
            } else {
                // By default, we exclude deleted payment methods as they are usually not relevant
                // for active operations.
                $query->setFilter($this->createFilter('state', SdkCreationEntityState::DELETED, SdkCriteriaOperator::NOT_EQUALS));
            }

            $results = $service->search($spaceId, $query);

            return new PaymentMethodCollection(...array_map([$this, 'mapToPaymentMethod'], $results));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch payment methods from SDK.', [
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw new PaymentMethodException(
                'Failed to fetch payment methods: ' . $e->getMessage(),
                new LocalizedString('The payment methods could not be retrieved.'),
                $e,
            );
        }
    }

}
