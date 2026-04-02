<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PostFinanceCheckout\PluginCore\DeliveryIndication\State as PluginCoreDeliveryIndicationState;
use PostFinanceCheckout\PluginCore\ManualTask\State as PluginCoreManualTaskState;
use PostFinanceCheckout\PluginCore\Refund\State as PluginCoreRefundState;
use PostFinanceCheckout\PluginCore\Token\Version\State as PluginCoreTokenVersionState;
use PostFinanceCheckout\PluginCore\Transaction\Completion\State as PluginCoreTransactionCompletionState;
use PostFinanceCheckout\PluginCore\Transaction\Invoice\State as PluginCoreTransactionInvoiceState;
use PostFinanceCheckout\PluginCore\Transaction\State as PluginCoreTransactionState;
use PostFinanceCheckout\PluginCore\Transaction\Void\State as PluginCoreTransactionVoidState;
use PostFinanceCheckout\Sdk\Model\DeliveryIndicationState as SdkDeliveryIndicationState;
use PostFinanceCheckout\Sdk\Model\ManualTaskState as SdkManualTaskState;
use PostFinanceCheckout\Sdk\Model\RefundState as SdkRefundState;
use PostFinanceCheckout\Sdk\Model\TokenVersionState as SdkTokenVersionState;
use PostFinanceCheckout\Sdk\Model\TransactionCompletionState as SdkTransactionCompletionState;
use PostFinanceCheckout\Sdk\Model\TransactionInvoiceState as SdkTransactionInvoiceState;
use PostFinanceCheckout\Sdk\Model\TransactionState as SdkTransactionState;
use PostFinanceCheckout\Sdk\Model\TransactionVoidState as SdkTransactionVoidState;

class StateSynchronizationTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: class-string}>
     */
    public static function stateMappingProvider(): array
    {
        return [
            'Delivery Indication States' => [
                SdkDeliveryIndicationState::class,
                PluginCoreDeliveryIndicationState::class,
            ],
            'Refund States' => [
                SdkRefundState::class,
                PluginCoreRefundState::class,
            ],
            'Manual Task States' => [
                SdkManualTaskState::class,
                PluginCoreManualTaskState::class,
            ],
            'Token Version States' => [
                SdkTokenVersionState::class,
                PluginCoreTokenVersionState::class,
            ],
            'Transaction States' => [
                SdkTransactionState::class,
                PluginCoreTransactionState::class,
            ],
            'Transaction Completion States' => [
                SdkTransactionCompletionState::class,
                PluginCoreTransactionCompletionState::class,
            ],
            'Transaction Invoice States' => [
                SdkTransactionInvoiceState::class,
                PluginCoreTransactionInvoiceState::class,
            ],
            'Transaction Void States' => [
                SdkTransactionVoidState::class,
                PluginCoreTransactionVoidState::class,
            ],
        ];
    }

    #[DataProvider('stateMappingProvider')]
    public function testInternalEnumCoversAllSdkStates(string $sdkStateClass, string $internalEnumClass): void
    {
        $sdkStates = $sdkStateClass::getAllowableEnumValues();
        $internalEnumValues = array_map(fn ($case) => $case->value, $internalEnumClass::cases());

        foreach ($sdkStates as $sdkState) {
            $this->assertContains(
                $sdkState,
                $internalEnumValues,
                "SDK state '{$sdkState}' is missing from internal enum {$internalEnumClass}",
            );
        }
    }
}
