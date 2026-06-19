<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV1;

use PostFinanceCheckout\PluginCore\Sdk\FailureReasonMapperTrait;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Transaction\Completion\State as StateEnum;
use PostFinanceCheckout\PluginCore\Transaction\Completion\TransactionCompletion;
use PostFinanceCheckout\PluginCore\Transaction\Completion\TransactionCompletionGatewayInterface;
use PostFinanceCheckout\PluginCore\Transaction\Void\State as VoidStateEnum;
use PostFinanceCheckout\PluginCore\Transaction\Void\TransactionVoid;
use PostFinanceCheckout\Sdk\Model\TransactionCompletion as SdkTransactionCompletion;
use PostFinanceCheckout\Sdk\Service\TransactionCompletionService as SdkTransactionCompletionService;
use PostFinanceCheckout\Sdk\Service\TransactionVoidService as SdkTransactionVoidService;

/**
 * SDK v1 implementation of the transaction completion gateway.
 *
 * This class interacts with the PostFinanceCheckout SDK to perform capture operations
 * and maps SDK objects to domain entities.
 */
class TransactionCompletionGateway implements TransactionCompletionGatewayInterface
{
    use FailureReasonMapperTrait;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
    ) {
    }

    /**
     * Captures an authorized transaction by creating a completion online.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID to capture.
     * @return TransactionCompletion The resulting completion domain object.
     */
    public function capture(int $spaceId, int $transactionId): TransactionCompletion
    {
        /** @var SdkTransactionCompletionService $service */
        $service = $this->sdkProvider->getService(SdkTransactionCompletionService::class);

        // Call the SDK to create the completion online (immediate capture)
        $sdkResult = $service->completeOnline($spaceId, $transactionId);

        // Map the SDK result to our domain entity
        return $this->mapToTransactionCompletion($sdkResult);
    }

    /**
     * Maps an SDK TransactionCompletion to our domain TransactionCompletion.
     *
     * This ensures SDK objects do not leak into the domain layer.
     *
     * @param SdkTransactionCompletion $sdkCompletion The SDK completion object.
     * @return TransactionCompletion The domain completion object.
     */
    private function mapToTransactionCompletion(SdkTransactionCompletion $sdkCompletion): TransactionCompletion
    {
        $completion = new TransactionCompletion();

        $completion->id = $sdkCompletion->getId();
        $completion->linkedTransactionId = $sdkCompletion->getLinkedTransaction();
        $completion->state = StateEnum::from($sdkCompletion->getState());

        $reason = $sdkCompletion->getFailureReason();
        if ($reason !== null) {
            $completion->failureReason = $this->mapSdkFailureReason($reason);
        }

        if ($sdkCompletion->getLineItems()) {
            $completion->lineItems = array_map(function ($sdkItem) {
                $item = new \PostFinanceCheckout\PluginCore\LineItem\LineItem();
                $item->uniqueId = $sdkItem->getUniqueId();
                $item->sku = $sdkItem->getSku();
                $item->name = $sdkItem->getName();
                $item->quantity = $sdkItem->getQuantity();
                $item->amountIncludingTax = $sdkItem->getAmountIncludingTax();
                $item->type = match ($sdkItem->getType()) {
                    \PostFinanceCheckout\Sdk\Model\LineItemType::DISCOUNT => \PostFinanceCheckout\PluginCore\LineItem\LineItem::TYPE_DISCOUNT,
                    \PostFinanceCheckout\Sdk\Model\LineItemType::SHIPPING => \PostFinanceCheckout\PluginCore\LineItem\LineItem::TYPE_SHIPPING,
                    \PostFinanceCheckout\Sdk\Model\LineItemType::FEE => \PostFinanceCheckout\PluginCore\LineItem\LineItem::TYPE_FEE,
                    default => \PostFinanceCheckout\PluginCore\LineItem\LineItem::TYPE_PRODUCT,
                };
                return $item;
            }, $sdkCompletion->getLineItems());
        }

        return $completion;
    }
    /**
     * Voids an authorized transaction.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID to void.
     * @return TransactionVoid The resulting void domain object.
     */
    public function void(int $spaceId, int $transactionId): TransactionVoid
    {
        /** @var SdkTransactionVoidService $service */
        $service = $this->sdkProvider->getService(SdkTransactionVoidService::class);

        $sdkVoid = $service->voidOnline($spaceId, $transactionId);

        $void = new TransactionVoid();
        $void->state = VoidStateEnum::from((string)$sdkVoid->getState());

        $reason = $sdkVoid->getFailureReason();
        if ($reason !== null) {
            $void->failureReason = $this->mapSdkFailureReason($reason);
        }

        return $void;
    }
}
