<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\SdkV1;

use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\PaymentMethod\PaymentMethod;
use PostFinanceCheckout\PluginCore\PaymentMethod\PaymentMethodGatewayInterface;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\Sdk\Model\EntityQuery as SdkEntityQuery;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilter as SdkEntityQueryFilter;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilterType as SdkEntityQueryFilterType;
use PostFinanceCheckout\Sdk\Model\CriteriaOperator as SdkCriteriaOperator;
use PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration as SdkPaymentMethodConfiguration;
use PostFinanceCheckout\Sdk\Service\PaymentMethodConfigurationService as SdkPaymentMethodConfigurationService;

/**
 * Gateway implementation using the SDK.
 */
class PaymentMethodGateway implements PaymentMethodGatewayInterface
{
    /**
     * @param SdkProvider $provider The SDK provider.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly SdkProvider $provider,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @inheritDoc
     */
    public function fetchById(int $spaceId, int $id): PaymentMethod
    {
        try {
            /** @var SdkPaymentMethodConfigurationService $service */
            $service = $this->provider->getService(SdkPaymentMethodConfigurationService::class);

            $config = $service->read($spaceId, $id);

            return $this->mapToEntity($config);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to fetch payment method %d from SDK: %s', $id, $e->getMessage()));
            throw new \RuntimeException(sprintf('Payment method %d not found.', $id), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function fetchBySpaceId(int $spaceId, ?string $state = null): array
    {
        try {
            /** @var SdkPaymentMethodConfigurationService $service */
            $service = $this->provider->getService(SdkPaymentMethodConfigurationService::class);

            $query = new SdkEntityQuery();

            if ($state !== null) {
                $filter = new SdkEntityQueryFilter();
                $filter->setFieldName('state');
                $filter->setValue($state);
                $filter->setOperator(SdkCriteriaOperator::EQUALS);
                $filter->setType(SdkEntityQueryFilterType::LEAF);
                $query->setFilter($filter);
            }

            $results = $service->search($spaceId, $query);

            return array_map(fn(SdkPaymentMethodConfiguration $config) => $this->mapToEntity($config), $results);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to fetch payment methods from SDK: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Maps an SDK configuration to a domain entity.
     *
     * @param SdkPaymentMethodConfiguration $config The SDK configuration.
     * @return PaymentMethod The domain entity.
     */
    private function mapToEntity(SdkPaymentMethodConfiguration $config): PaymentMethod
    {
        return new PaymentMethod(
            id: $config->getId(),
            spaceId: $config->getLinkedSpaceId(),
            state: (string) $config->getState(), // Cast to string as state is usually an enum or string
            //TODO: We need to check how to support different language codes.
            name: $this->resolveLocalization($config->getResolvedTitle() ?? $config->getName()),
            title: $config->getResolvedTitle() ?? [],
            description: $this->resolveLocalization($config->getResolvedDescription() ?? $config->getDescription()),
            descriptionMap: $config->getResolvedDescription() ?? $config->getDescription() ?? [],
            sortOrder: $config->getSortOrder(),
            imageUrl: $config->getResolvedImageUrl(), // Assuming this exists or similar
        );
    }

    /**
     * Resolves a localized string (which might be an array) to a single string.
     *
     * @param array<string, string>|string|null $input
     * @return string|null
     */
    private function resolveLocalization(array|string|null $input): ?string
    {
        if (is_string($input) || is_null($input)) {
            return $input;
        }

        if (is_array($input)) {
            // Prefer English
            if (isset($input['en-US'])) {
                return $input['en-US'];
            }
            if (isset($input['en-GB'])) {
                return $input['en-GB'];
            }
            // Fallback to first available
            return reset($input) ?: null;
        }

        return null;
    }
}
