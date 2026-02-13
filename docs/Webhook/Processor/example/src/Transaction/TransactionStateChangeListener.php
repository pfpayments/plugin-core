<?php

declare(strict_types=1);

namespace MyPlugin\ExampleWebhookImplementation\Transaction;

use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Webhook\Command\WebhookCommandInterface;
use PostFinanceCheckout\PluginCore\Webhook\Listener\WebhookListenerInterface;
use PostFinanceCheckout\PluginCore\Webhook\WebhookContext;

class TransactionStateChangeListener implements WebhookListenerInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {}

    public function getCommand(WebhookContext $context): WebhookCommandInterface
    {
        return new UpdateTransactionStateCommand($context, $this->logger);
    }
}
