<?php
declare(strict_types=1);
namespace MyPlugin\ExampleWebhookImplementation\Transaction;

use PostFinanceCheckout\PluginCore\Webhook\Listener\WebhookListenerInterface;
use PostFinanceCheckout\PluginCore\Webhook\Command\WebhookCommandInterface;
use PostFinanceCheckout\PluginCore\Webhook\WebhookContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Transaction\State as StateEnum;

class TransactionListener implements WebhookListenerInterface {
    public function __construct(private readonly LoggerInterface $logger) {}

    public function getCommand(WebhookContext $context): WebhookCommandInterface {
        // Route to specific commands based on state
        return match ($context->remoteState) {
            StateEnum::AUTHORIZED->value => new AuthorizedCommand($context, $this->logger),
            StateEnum::FULFILL->value    => new FulfillCommand($context, $this->logger),
            default                  => new GenericCommand($context, $this->logger),
        };
    }
}
