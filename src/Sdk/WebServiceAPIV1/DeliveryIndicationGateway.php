<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV1;

use PostFinanceCheckout\PluginCore\DeliveryIndication\DeliveryIndication;
use PostFinanceCheckout\PluginCore\DeliveryIndication\DeliveryIndicationGatewayInterface;
use PostFinanceCheckout\PluginCore\DeliveryIndication\Exception\DeliveryIndicationException;
use PostFinanceCheckout\PluginCore\Log\DomainLoggerTrait;
use PostFinanceCheckout\PluginCore\Log\LogContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Sdk\DeliveryIndicationMapperTrait;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\Sdk\Model\CriteriaOperator as SdkCriteriaOperator;
use PostFinanceCheckout\Sdk\Model\DeliveryIndication as SdkDeliveryIndication;
use PostFinanceCheckout\Sdk\Model\EntityQuery as SdkEntityQuery;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilter as SdkEntityQueryFilter;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilterType as SdkEntityQueryFilterType;
use PostFinanceCheckout\Sdk\Service\DeliveryIndicationService as SdkDeliveryIndicationService;

/**
 * Gateway for reading and deciding delivery indications using the SDK.
 *
 * This SDK takes `($spaceId, $indicationId)`, which matches the domain
 * interface's order, so the arguments are passed straight through. It reports
 * the completion as a bare ID, and searches with an entity query object.
 */
#[LogContext(domain: 'delivery_indication')]
class DeliveryIndicationGateway implements DeliveryIndicationGatewayInterface
{
    use DeliveryIndicationMapperTrait;
    use DomainLoggerTrait;

    private SdkDeliveryIndicationService $service;

    /**
     * @param SdkProvider $sdkProvider The SDK provider.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
        $this->service = $this->sdkProvider->getService(SdkDeliveryIndicationService::class);
    }

    /**
     * @inheritDoc
     */
    public function findByTransaction(int $spaceId, int $transactionId): ?DeliveryIndication
    {
        $operation = 'search';
        $context = ['spaceId' => $spaceId, 'transactionId' => $transactionId];

        $filter = new SdkEntityQueryFilter();
        $filter->setType(SdkEntityQueryFilterType::LEAF);
        $filter->setOperator(SdkCriteriaOperator::EQUALS);
        $filter->setFieldName('transaction.id');
        $filter->setValue($transactionId);

        $query = new SdkEntityQuery();
        $query->setFilter($filter);
        // A transaction has at most one delivery indication.
        $query->setNumberOfEntities(1);

        $this->logger->debug('Calling delivery indication operation.', ['operation' => $operation] + $context);

        try {
            $results = $this->service->search($spaceId, $query);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Delivery indication operation failed.',
                ['operation' => $operation, 'errorMessage' => $e->getMessage(), 'exception' => $e] + $context,
            );

            throw SdkProvider::wrapException(
                $e,
                DeliveryIndicationException::class,
                $operation,
                $context,
                'An error occurred while processing the delivery indication.',
            );
        }

        if (!is_array($results)) {
            $this->logger->error(
                'Delivery indication operation returned an unexpected response.',
                ['operation' => $operation, 'responseType' => get_debug_type($results)] + $context,
            );

            throw SdkProvider::unexpectedResponseException(
                DeliveryIndicationException::class,
                $operation,
                $context,
                'An error occurred while processing the delivery indication.',
            );
        }

        $sdkIndication = $results[0] ?? null;

        if ($sdkIndication === null) {
            // A transaction with no delivery indication is an ordinary outcome: the
            // payment may not be captured, or may not have been selected for review.
            $this->logger->info(
                'No delivery indication found for the transaction.',
                ['operation' => $operation] + $context,
            );

            return null;
        }

        $this->validateDeliveryIndicationResponse($sdkIndication, $operation, $context);

        return $this->mapToDeliveryIndication($sdkIndication, $spaceId);
    }

    /**
     * @inheritDoc
     */
    public function get(int $spaceId, int $indicationId): DeliveryIndication
    {
        $operation = 'read';
        $context = ['spaceId' => $spaceId, 'indicationId' => $indicationId];

        $this->logger->debug('Calling delivery indication operation.', ['operation' => $operation] + $context);

        try {
            $result = $this->service->read($spaceId, $indicationId);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Delivery indication operation failed.',
                ['operation' => $operation, 'errorMessage' => $e->getMessage(), 'exception' => $e] + $context,
            );

            throw SdkProvider::wrapException(
                $e,
                DeliveryIndicationException::class,
                $operation,
                $context,
                'An error occurred while processing the delivery indication.',
            );
        }

        $this->validateDeliveryIndicationResponse($result, $operation, $context);

        return $this->mapToDeliveryIndication($result, $spaceId);
    }

    /**
     * @inheritDoc
     */
    public function markAsNotSuitable(int $spaceId, int $indicationId): DeliveryIndication
    {
        $operation = 'markAsNotSuitable';
        $context = ['spaceId' => $spaceId, 'indicationId' => $indicationId];

        $this->logger->debug('Calling delivery indication operation.', ['operation' => $operation] + $context);

        try {
            $result = $this->service->markAsNotSuitable($spaceId, $indicationId);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Delivery indication operation failed.',
                ['operation' => $operation, 'errorMessage' => $e->getMessage(), 'exception' => $e] + $context,
            );

            throw SdkProvider::wrapException(
                $e,
                DeliveryIndicationException::class,
                $operation,
                $context,
                'An error occurred while processing the delivery indication.',
            );
        }

        $this->validateDeliveryIndicationResponse($result, $operation, $context);

        $indication = $this->mapToDeliveryIndication($result, $spaceId);

        $this->logger->info(
            'Delivery indication operation succeeded.',
            ['operation' => $operation, 'state' => $indication->state->value] + $context,
        );

        return $indication;
    }

    /**
     * @inheritDoc
     */
    public function markAsSuitable(int $spaceId, int $indicationId): DeliveryIndication
    {
        $operation = 'markAsSuitable';
        $context = ['spaceId' => $spaceId, 'indicationId' => $indicationId];

        $this->logger->debug('Calling delivery indication operation.', ['operation' => $operation] + $context);

        try {
            $result = $this->service->markAsSuitable($spaceId, $indicationId);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Delivery indication operation failed.',
                ['operation' => $operation, 'errorMessage' => $e->getMessage(), 'exception' => $e] + $context,
            );

            throw SdkProvider::wrapException(
                $e,
                DeliveryIndicationException::class,
                $operation,
                $context,
                'An error occurred while processing the delivery indication.',
            );
        }

        $this->validateDeliveryIndicationResponse($result, $operation, $context);

        $indication = $this->mapToDeliveryIndication($result, $spaceId);

        $this->logger->info(
            'Delivery indication operation succeeded.',
            ['operation' => $operation, 'state' => $indication->state->value] + $context,
        );

        return $indication;
    }

    /**
     * Validates that a raw SDK response is a delivery indication.
     *
     * @param mixed $result The raw SDK response.
     * @param string $operation The SDK operation name, for log context.
     * @param array<string, mixed> $context Identifying context for the log records.
     * @return void
     * @throws DeliveryIndicationException If the response was not a delivery indication.
     *
     * @phpstan-assert SdkDeliveryIndication $result
     */
    private function validateDeliveryIndicationResponse(mixed $result, string $operation, array $context): void
    {
        if (!$result instanceof SdkDeliveryIndication) {
            $this->logger->error(
                'Delivery indication operation returned an unexpected response.',
                ['operation' => $operation, 'responseType' => get_debug_type($result)] + $context,
            );

            throw SdkProvider::unexpectedResponseException(
                DeliveryIndicationException::class,
                $operation,
                $context,
                'An error occurred while processing the delivery indication.',
            );
        }
    }
}
