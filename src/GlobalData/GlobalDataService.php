<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\GlobalData;

use PostFinanceCheckout\PluginCore\GlobalData\Currency\CurrencyCollection;
use PostFinanceCheckout\PluginCore\GlobalData\Exception\GlobalDataException;
use PostFinanceCheckout\PluginCore\GlobalData\LabelDescriptor\LabelDescriptorCollection;
use PostFinanceCheckout\PluginCore\GlobalData\LabelDescriptorGroup\LabelDescriptorGroupCollection;
use PostFinanceCheckout\PluginCore\GlobalData\Language\LanguageCollection;
use PostFinanceCheckout\PluginCore\GlobalData\PaymentConnector\PaymentConnectorCollection;
use PostFinanceCheckout\PluginCore\Log\DomainLoggerTrait;
use PostFinanceCheckout\PluginCore\Log\LogContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;

/**
 * Domain-facing facade for the PostFinanceCheckout Portal's global reference data.
 *
 * This is the single entry point consumers use for currencies, languages,
 * payment connectors, and label descriptors and their groups. It delegates to the
 * configured {@see GlobalDataGatewayInterface}, which owns the API interaction,
 * its logging and its failure handling. The service deliberately adds no logging
 * or exception wrapping of its own: doing so would record every failure twice and
 * re-wrap exceptions that are already domain exceptions.
 *
 * None of these methods take a space ID — this data is global to the PostFinanceCheckout Portal, not
 * scoped to a merchant.
 *
 * Queries over data already read — resolving a locale to its primary variant, or
 * looking an entity up by ID — live on the returned collections rather than here,
 * so they cost nothing beyond the one read that produced the collection.
 */
#[LogContext(domain: 'global_data')]
class GlobalDataService
{
    use DomainLoggerTrait;

    public function __construct(
        private readonly GlobalDataGatewayInterface $gateway,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
    }

    /**
     * Returns every currency the PostFinanceCheckout Portal supports.
     *
     * @return CurrencyCollection The supported currencies.
     * @throws GlobalDataException If the currencies cannot be retrieved.
     */
    public function getCurrencies(): CurrencyCollection
    {
        return $this->gateway->getCurrencies();
    }

    /**
     * Returns every label descriptor group the PostFinanceCheckout Portal defines.
     *
     * @return LabelDescriptorGroupCollection The label descriptor groups.
     * @throws GlobalDataException If the groups cannot be retrieved.
     */
    public function getLabelDescriptorGroups(): LabelDescriptorGroupCollection
    {
        return $this->gateway->getLabelDescriptorGroups();
    }

    /**
     * Returns every label descriptor the PostFinanceCheckout Portal defines.
     *
     * @return LabelDescriptorCollection The label descriptors.
     * @throws GlobalDataException If the descriptors cannot be retrieved.
     */
    public function getLabelDescriptors(): LabelDescriptorCollection
    {
        return $this->gateway->getLabelDescriptors();
    }

    /**
     * Returns every language the PostFinanceCheckout Portal supports.
     *
     * @return LanguageCollection The supported languages.
     * @throws GlobalDataException If the languages cannot be retrieved.
     */
    public function getLanguages(): LanguageCollection
    {
        return $this->gateway->getLanguages();
    }

    /**
     * Returns every payment connector the PostFinanceCheckout Portal defines.
     *
     * @return PaymentConnectorCollection The payment connectors.
     * @throws GlobalDataException If the connectors cannot be retrieved.
     */
    public function getPaymentConnectors(): PaymentConnectorCollection
    {
        return $this->gateway->getPaymentConnectors();
    }
}
