<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Token\Exception;

use PostFinanceCheckout\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when token creation fails at the API or transport level.
 */
class TokenException extends AbstractDomainException
{
}
