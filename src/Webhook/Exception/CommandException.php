<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Webhook\Exception;

use PostFinanceCheckout\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Base exception for errors that occur during a webhook command execution.
 */
class CommandException extends AbstractDomainException
{
}
