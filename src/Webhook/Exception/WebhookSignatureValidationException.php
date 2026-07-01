<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Webhook\Exception;

use PostFinanceCheckout\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when webhook signature verification fails due to API or network errors.
 */
class WebhookSignatureValidationException extends AbstractDomainException
{
}
