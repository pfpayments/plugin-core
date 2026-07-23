<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Transaction\Completion\Exception;

use PostFinanceCheckout\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Base exception for errors that occur during capture (completion) or void operations.
 */
class CompletionException extends AbstractDomainException
{
}
