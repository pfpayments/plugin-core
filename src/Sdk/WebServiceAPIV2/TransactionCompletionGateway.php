<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV2;

use PostFinanceCheckout\PluginCore\LineItem\LineItem;
use PostFinanceCheckout\PluginCore\Localization\LocalizedString;
use PostFinanceCheckout\PluginCore\Log\DomainLoggerTrait;
use PostFinanceCheckout\PluginCore\Log\LogContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Sdk\FailureReasonMapperTrait;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Transaction\Completion\CaptureRequest;
use PostFinanceCheckout\PluginCore\Transaction\Completion\Exception\CompletionException;
use PostFinanceCheckout\PluginCore\Transaction\Completion\State;
use PostFinanceCheckout\PluginCore\Transaction\Completion\TransactionCompletion;
use PostFinanceCheckout\PluginCore\Transaction\Completion\TransactionCompletionGatewayInterface;
use PostFinanceCheckout\PluginCore\Transaction\Void\State as VoidState;
use PostFinanceCheckout\PluginCore\Transaction\Void\TransactionVoid;
use PostFinanceCheckout\Sdk\ApiException;
use PostFinanceCheckout\Sdk\Model\CompletionLineItemCreate as SdkCompletionLineItemCreate;
use PostFinanceCheckout\Sdk\Model\TransactionCompletion as SdkTransactionCompletion;
use PostFinanceCheckout\Sdk\Model\TransactionCompletionDetails as SdkTransactionCompletionDetails;
use PostFinanceCheckout\Sdk\Service\TransactionCompletionsService as SdkTransactionCompletionsService;
use PostFinanceCheckout\Sdk\Service\TransactionsService as SdkTransactionsService;

/**
 * SDK v2 implementation of the transaction completion gateway.
 *
 * This class interacts with the PostFinanceCheckout SDK to perform capture and void operations.
 */
#[LogContext(domain: 'transaction', subdomain: 'completion')]
class TransactionCompletionGateway implements TransactionCompletionGatewayInterface
{
    use DomainLoggerTrait;
    use FailureReasonMapperTrait;

    private SdkTransactionsService $transactionsService;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
        $this->transactionsService = $this->sdkProvider->getService(SdkTransactionsService::class);
    }

    /**
     * Captures an authorized transaction by creating a completion, fully or partially.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID to capture.
     * @param CaptureRequest|null $request The capture details, or null for a full capture.
     * @return TransactionCompletion The resulting completion domain object.
     * @throws CompletionException If the capture fails.
     */
    public function capture(
        int $spaceId,
        int $transactionId,
        ?CaptureRequest $request = null,
    ): TransactionCompletion {
        $isPartial = $request !== null && !$request->lineItems->isEmpty();
        $this->logger->debug('Gateway: Capturing transaction.', [
            'transactionId' => $transactionId,
            'spaceId' => $spaceId,
            'partial' => $isPartial,
        ]);

        try {
            if (!$isPartial) {
                // No specific line items requested: capture the full remaining amount.
                $sdkResult = $this->transactionsService->postPaymentTransactionsIdCompleteOnline($transactionId, $spaceId);
            } else {
                $sdkDetails = new SdkTransactionCompletionDetails();
                $sdkDetails->setLastCompletion($request->isFinal);
                // Guaranteed non-null for a partial capture: CaptureRequest rejects
                // a partial request without one, since the API requires it.
                $sdkDetails->setExternalId((string)$request->externalId);
                if ($request->merchantReference !== null) {
                    $sdkDetails->setInvoiceMerchantReference($request->merchantReference);
                }
                $sdkDetails->setLineItems(array_map(
                    static function (LineItem $item): SdkCompletionLineItemCreate {
                        // Only uniqueId, quantity and amount are sent for a capture, so a
                        // capture line item needs nothing else set. Deliberately no
                        // sanitize() call here: it touches name/sku, which are neither
                        // required nor transmitted, and would fail on a minimal item.
                        $sdkItem = new SdkCompletionLineItemCreate();
                        $sdkItem->setUniqueId($item->uniqueId);
                        $sdkItem->setQuantity($item->quantity);
                        $sdkItem->setAmount($item->amountIncludingTax);
                        return $sdkItem;
                    },
                    iterator_to_array($request->lineItems),
                ));

                // V2: postPaymentTransactionsIdCompletePartiallyOnline($id, $space, $transaction_completion_details)
                $sdkResult = $this->transactionsService->postPaymentTransactionsIdCompletePartiallyOnline($transactionId, $spaceId, $sdkDetails);
            }

            return $this->mapToTransactionCompletion($sdkResult);
        } catch (\Throwable $e) {
            $this->logger->error('Gateway: Failed to capture transaction.', [
                'exception' => $e,
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
            ]);
            throw SdkProvider::wrapException(
                $e,
                CompletionException::class,
                'postPaymentTransactionsIdCompleteOnline',
                ['spaceId' => $spaceId, 'transactionId' => $transactionId],
                'An error occurred while capturing the transaction.',
            );
        }
    }

    /**
     * Finds a completion by ID.
     *
     * @param int $spaceId The space ID.
     * @param int $completionId The completion ID.
     * @return TransactionCompletion|null The completion, or null if not found.
     * @throws CompletionException If the API request fails (non-404).
     */
    public function find(int $spaceId, int $completionId): ?TransactionCompletion
    {
        try {
            /** @var SdkTransactionCompletionsService $service */
            $service = $this->sdkProvider->getService(SdkTransactionCompletionsService::class);

            // V2: getPaymentTransactionsCompletionsId($id, $space)
            $sdkCompletion = $service->getPaymentTransactionsCompletionsId($completionId, $spaceId);

            // Defensive: treat an empty model (no ID) as not found as well.
            if ($sdkCompletion->getId() === null) {
                $this->logger->debug('Gateway: Completion not found.', [
                    'completionId' => $completionId,
                    'spaceId' => $spaceId,
                ]);
                return null;
            }

            return $this->mapToTransactionCompletion($sdkCompletion);
        } catch (\Throwable $e) {
            if ($e instanceof ApiException && $e->getCode() === 404) {
                $this->logger->debug('Gateway: Completion not found.', [
                    'completionId' => $completionId,
                    'spaceId' => $spaceId,
                ]);
                return null;
            }

            $this->logger->error('Gateway: Failed to find completion.', [
                'exception' => $e,
                'completionId' => $completionId,
                'spaceId' => $spaceId,
            ]);
            throw SdkProvider::wrapException(
                $e,
                CompletionException::class,
                'getPaymentTransactionsCompletionsId',
                ['spaceId' => $spaceId, 'completionId' => $completionId],
                'An error occurred while retrieving the completion.',
            );
        }
    }

    /**
     * Gets a completion by ID and throws if not found or failed.
     *
     * @param int $spaceId The space ID.
     * @param int $completionId The completion ID.
     * @return TransactionCompletion The completion.
     * @throws CompletionException If the completion is not found or the request fails.
     */
    public function get(int $spaceId, int $completionId): TransactionCompletion
    {
        $this->logger->debug('Gateway: Reading completion.', [
            'completionId' => $completionId,
            'spaceId' => $spaceId,
        ]);

        try {
            /** @var SdkTransactionCompletionsService $service */
            $service = $this->sdkProvider->getService(SdkTransactionCompletionsService::class);

            // V2: getPaymentTransactionsCompletionsId($id, $space)
            $sdkCompletion = $service->getPaymentTransactionsCompletionsId($completionId, $spaceId);

            // Defensive: treat an empty model (no ID) as not found as well.
            if ($sdkCompletion->getId() === null) {
                throw new CompletionException(
                    "Completion {$completionId} not found in space {$spaceId}.",
                    new LocalizedString('The completion could not be found.'),
                );
            }

            return $this->mapToTransactionCompletion($sdkCompletion);
        } catch (CompletionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Gateway: Failed to read completion.', [
                'exception' => $e,
                'completionId' => $completionId,
                'spaceId' => $spaceId,
            ]);
            throw SdkProvider::wrapException(
                $e,
                CompletionException::class,
                'getPaymentTransactionsCompletionsId',
                ['spaceId' => $spaceId, 'completionId' => $completionId],
                'An error occurred while retrieving the completion.',
            );
        }
    }

    /**
     * Maps an SDK TransactionCompletion to our domain TransactionCompletion.
     *
     * @param SdkTransactionCompletion $sdkCompletion The SDK completion object.
     * @return TransactionCompletion The domain completion object.
     */
    private function mapToTransactionCompletion(SdkTransactionCompletion $sdkCompletion): TransactionCompletion
    {
        $completion = new TransactionCompletion();

        $completion->id = $sdkCompletion->getId();
        $completion->linkedTransactionId = $sdkCompletion->getLinkedTransaction();
        if ($sdkCompletion->getState()) {
            $completion->state = State::from((string)$sdkCompletion->getState());
        }

        $completion->lineItems = $sdkCompletion->getLineItems() ?? [];

        $reason = $sdkCompletion->getFailureReason();
        if ($reason !== null) {
            $completion->failureReason = $this->mapSdkFailureReason($reason);
        }

        return $completion;
    }

    /**
     * Voids an authorized transaction.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID to void.
     * @return TransactionVoid The resulting void domain object.
     * @throws CompletionException If the void fails.
     */
    public function void(
        int $spaceId,
        int $transactionId,
    ): TransactionVoid {
        try {
            // Voids the transaction using the SDK's online void method.
            $sdkVoid = $this->transactionsService->postPaymentTransactionsIdVoidOnline(
                $transactionId,
                $spaceId,
            );

            $void = new TransactionVoid();
            if ($sdkVoid->getState()) {
                $void->state = VoidState::from((string)$sdkVoid->getState());
            }

            $reason = $sdkVoid->getFailureReason();
            if ($reason !== null) {
                $void->failureReason = $this->mapSdkFailureReason($reason);
            }

            return $void;
        } catch (\Throwable $e) {
            $this->logger->error('Gateway: Failed to void transaction.', [
                'exception' => $e,
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
            ]);
            throw SdkProvider::wrapException(
                $e,
                CompletionException::class,
                'postPaymentTransactionsIdVoidOnline',
                ['spaceId' => $spaceId, 'transactionId' => $transactionId],
                'An error occurred while voiding the transaction.',
            );
        }
    }
}
