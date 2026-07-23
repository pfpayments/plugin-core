<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Webhook\Exception;

use PostFinanceCheckout\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Base exception for errors that occur while managing webhook URLs and listeners.
 */
class WebhookManagementException extends AbstractDomainException
{
}
