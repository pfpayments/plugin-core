<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Transaction\Invoice\Exception;

use PostFinanceCheckout\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Base exception for errors that occur while reading transaction invoices.
 */
class InvoiceException extends AbstractDomainException
{
}
