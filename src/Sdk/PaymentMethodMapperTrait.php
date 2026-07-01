<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk;

use PostFinanceCheckout\PluginCore\Localization\LocalizedString;
use PostFinanceCheckout\PluginCore\PaymentMethod\PaymentMethod;
use PostFinanceCheckout\PluginCore\PaymentMethod\State;
use PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration as SdkPaymentMethodConfiguration;

/**
 * Shared mapping for SDK PaymentMethodConfiguration objects to domain PaymentMethod entities.
 *
 * Centralizes locale-aware title/description resolution (delegated to LocalizedString)
 * so the transaction and payment-method gateways map configurations identically.
 */
trait PaymentMethodMapperTrait
{
    /**
     * Maps an SDK PaymentMethodConfiguration to a domain PaymentMethod.
     *
     * The LocalizedString VO takes ownership of locale resolution, so the raw SDK
     * maps are passed directly without pre-resolving.
     *
     * @param SdkPaymentMethodConfiguration $config The SDK configuration.
     * @return PaymentMethod The domain entity.
     */
    protected function mapToPaymentMethod(SdkPaymentMethodConfiguration $config): PaymentMethod
    {
        return new PaymentMethod(
            id: (int) $config->getId(),
            spaceId: (int) $config->getLinkedSpaceId(),
            state: State::from((string) $config->getState()),
            title: new LocalizedString($config->getResolvedTitle() ?? $config->getName()),
            description: new LocalizedString($config->getResolvedDescription() ?? $config->getDescription()),
            sortOrder: (int) $config->getSortOrder(),
            imageUrl: $config->getResolvedImageUrl(),
        );
    }
}
