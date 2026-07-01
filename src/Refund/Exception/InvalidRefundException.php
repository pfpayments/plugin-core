<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Refund\Exception;

use PostFinanceCheckout\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when a refund request violates a business rule before reaching the gateway.
 */
class InvalidRefundException extends AbstractDomainException
{
}
