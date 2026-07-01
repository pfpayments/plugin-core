<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Webhook\Exception;

use PostFinanceCheckout\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when a webhook processing step is skipped intentionally.
 */
class SkippedStepException extends AbstractDomainException
{
}
