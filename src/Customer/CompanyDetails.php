<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Customer;

use PostFinanceCheckout\PluginCore\SharedKernel\JsonStringableTrait;
use PostFinanceCheckout\PluginCore\SharedKernel\StringSanitizer;

/**
 * Identity data describing the customer as a legal entity, independent of any
 * postal address.
 *
 * `$organizationName` is truncated to 100 characters, matching the gateway's
 * field constraints — this DTO is immutable, so it is self-sanitized in the
 * constructor rather than via a later `sanitize()` call.
 */
class CompanyDetails
{
    use JsonStringableTrait;

    public readonly ?string $organizationName;

    public function __construct(
        public readonly ?string $commercialRegisterNumber = null,
        ?string $organizationName = null,
        public readonly ?string $salesTaxNumber = null,
    ) {
        $this->organizationName = $organizationName !== null ? StringSanitizer::truncate($organizationName, 100) : null;
    }
}
