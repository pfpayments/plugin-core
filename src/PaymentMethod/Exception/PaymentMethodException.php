<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\PaymentMethod\Exception;

use PostFinanceCheckout\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Base exception for errors that occur while fetching payment methods.
 */
class PaymentMethodException extends AbstractDomainException
{
}
