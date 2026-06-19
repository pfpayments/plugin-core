<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV1;

use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Sdk\TransactionMapperTrait;
use PostFinanceCheckout\PluginCore\Transaction\RecurringTransactionGatewayInterface;
use PostFinanceCheckout\PluginCore\Transaction\Transaction;
use PostFinanceCheckout\Sdk\Service\TransactionService as SdkTransactionService;

/**
 * Class RecurringTransactionGateway
 *
 * Implementation of the RecurringTransactionGatewayInterface using the SDK V1.
 */
class RecurringTransactionGateway implements RecurringTransactionGatewayInterface
{
    use TransactionMapperTrait;

    /**
     * @var SdkTransactionService The SDK transaction service.
     */
    private SdkTransactionService $transactionService;

    /**
     * RecurringTransactionGateway constructor.
     *
     * @param SdkProvider $sdkProvider The SDK provider.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
        private readonly LoggerInterface $logger,
    ) {
        $this->transactionService = $this->sdkProvider->getService(SdkTransactionService::class);
    }

    /**
     * Processes a recurring payment for an existing transaction.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID.
     * @return Transaction The processed transaction.
     * @throws \Exception If the processing fails.
     */
    public function processRecurringPayment(int $spaceId, int $transactionId): Transaction
    {
        $this->logger->debug("Processing recurring payment (ID: $transactionId).");

        try {
            $sdkTransaction = $this->transactionService->processWithoutUserInteraction($spaceId, $transactionId);
            $this->logger->debug("Recurring payment processed successfully for Transaction $transactionId.");

            return $this->mapToTransaction($sdkTransaction);
        } catch (\Exception $e) {
            $this->logger->error("Failed to process recurring payment for Transaction $transactionId: " . $e->getMessage());
            throw $e;
        }
    }
}
