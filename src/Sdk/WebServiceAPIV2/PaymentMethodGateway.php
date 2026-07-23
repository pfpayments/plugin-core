<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV2;

use PostFinanceCheckout\PluginCore\Localization\LocalizedString;
use PostFinanceCheckout\PluginCore\Log\DomainLoggerTrait;
use PostFinanceCheckout\PluginCore\Log\LogContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\PaymentMethod\PaymentMethod;
use PostFinanceCheckout\PluginCore\PaymentMethod\PaymentMethodCollection;
use PostFinanceCheckout\PluginCore\PaymentMethod\PaymentMethodGatewayInterface;
use PostFinanceCheckout\PluginCore\Sdk\PaymentMethodMapperTrait;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\PaymentMethod\Exception\PaymentMethodException;
use PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration as SdkPaymentMethodConfiguration;
use PostFinanceCheckout\Sdk\Service\PaymentMethodConfigurationsService as SdkPaymentMethodConfigurationService;

/**
 * Gateway implementation using the SDK V2.
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
     * @inheritDoc
     */
    public function fetchById(int $spaceId, int $id): PaymentMethod
    {
        try {
            /** @var SdkPaymentMethodConfigurationService $service */
            $service = $this->provider->getService(SdkPaymentMethodConfigurationService::class);

            // V2: getPaymentMethodConfigurationsId($id, $space)
            $config = $service->getPaymentMethodConfigurationsId($id, $spaceId);

            return $this->mapToPaymentMethod($config);
        } catch (\Throwable $e) {
            $this->logger->error("PaymentMethodGateway: Failed to fetch payment method from SDK.", [
                'paymentMethodId' => $id,
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw new PaymentMethodException(
                "Payment method {$id} not found: {$e->getMessage()}",
                new LocalizedString('Payment method not found.'),
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

            // V2 Search: query string
            $query = null;
            if ($state !== null) {
                // Filter by a specific state.
                $query = "state:$state";
            } else {
                // By default, exclude deleted payment methods to match V1 behavior.
                // In V2 query syntax, prepending '-' to a field name excludes the value.
                $query = "-state:DELETED";
            }

            // getPaymentMethodConfigurationsSearch signature: ($space, $expand, $limit, $offset, $order, $query)
            // We pass null for expand/limit/offset/order, and use query.
            $results = $service->getPaymentMethodConfigurationsSearch($spaceId, null, null, null, null, $query);

            $items = (is_object($results) && method_exists($results, 'getData')) ? $results->getData() : (array)$results;
            return new PaymentMethodCollection(...array_map([$this, 'mapToPaymentMethod'], $items));
        } catch (\Throwable $e) {
            $this->logger->error("PaymentMethodGateway: Failed to fetch payment methods from SDK.", [
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw new PaymentMethodException(
                "Unable to fetch payment methods: {$e->getMessage()}",
                new LocalizedString('Unable to fetch payment methods.'),
                $e,
            );
        }
    }

}
