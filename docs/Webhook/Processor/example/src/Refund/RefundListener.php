<?php
declare(strict_types=1);
namespace MyPlugin\ExampleWebhookImplementation\Refund;
use PostFinanceCheckout\PluginCore\Webhook\Listener\WebhookListenerInterface;
use PostFinanceCheckout\PluginCore\Webhook\Command\WebhookCommandInterface;
use PostFinanceCheckout\PluginCore\Webhook\WebhookContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;

class RefundListener implements WebhookListenerInterface {
    public function __construct(private readonly LoggerInterface $logger) {}
    public function getCommand(WebhookContext $context): WebhookCommandInterface {
        return new SuccessfulCommand($context, $this->logger);
    }
}
