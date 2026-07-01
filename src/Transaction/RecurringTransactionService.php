<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Transaction;

use PostFinanceCheckout\PluginCore\Localization\LocalizedString;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Token\Exception\MissingTokenException;
use PostFinanceCheckout\PluginCore\Transaction\Exception\TransactionException;
use PostFinanceCheckout\PluginCore\Transaction\Transaction;
use PostFinanceCheckout\PluginCore\Transaction\TransactionContext;
use PostFinanceCheckout\PluginCore\Transaction\TransactionService;

/**
 * Service for handling recurring transactions.
 */
readonly class RecurringTransactionService
{
    public function __construct(
        private TransactionService $transactionService,
        private RecurringTransactionGatewayInterface $recurringGateway,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Processes a recurring payment for an existing transaction.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID.
     * @return Transaction The processed transaction.
     * @throws \Throwable If processing fails.
     */
    public function processRecurringPayment(int $spaceId, int $transactionId): Transaction
    {
        $this->logger->debug("Processing recurring payment.", [
            'transactionId' => $transactionId,
            'spaceId' => $spaceId,
        ]);

        $originalTransaction = $this->transactionService->getTransaction($spaceId, $transactionId);

        // A token with stored payment credentials is required for recurring charges.
        // The original transaction must have been created with tokenizationMode = FORCE_CREATION
        // so the API automatically generates a token when the payment completes.
        if (!$originalTransaction->token) {
            $this->logger->error(
                "Transaction has no token. Recurring payments require the original transaction to have been created with tokenizationMode = FORCE_CREATION.",
                [
                    'transactionId' => $transactionId,
                ],
            );
            throw new MissingTokenException(
                "Transaction $transactionId has no token. "
                    . "The original transaction must be created with tokenizationMode = FORCE_CREATION "
                    . "to enable recurring payments.",
                new LocalizedString('The transaction has no token available for recurring payments.'),
            );
        }

        if ($originalTransaction->billingAddress === null) {
            throw new TransactionException(
                "Transaction $transactionId has no billing address.",
                new LocalizedString('The transaction is missing a billing address.'),
            );
        }

        $context = TransactionContext::fromTransaction($originalTransaction);

        $newTransaction = $this->transactionService->createTransaction($context);

        return $this->recurringGateway->processRecurringPayment($spaceId, $newTransaction->id);
    }
}
