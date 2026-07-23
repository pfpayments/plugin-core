<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Transaction\Completion;

use PostFinanceCheckout\PluginCore\Localization\LocalizedString;
use PostFinanceCheckout\PluginCore\Log\DomainLoggerTrait;
use PostFinanceCheckout\PluginCore\Log\LogContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Transaction\Exception\TransactionException;
use PostFinanceCheckout\PluginCore\Transaction\Void\TransactionVoid;

/**
 * Service for handling transaction completions (Capture, Void).
 */
#[LogContext(domain: 'transaction', subdomain: 'completion')]
class TransactionCompletionService
{
    use DomainLoggerTrait;
    public function __construct(
        private readonly TransactionCompletionGatewayInterface $completionGateway,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
    }

    /**
     * Captures an authorized transaction.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID to capture.
     * @return TransactionCompletion The resulting completion.
     * @throws TransactionException If the capture fails.
     */
    public function capture(int $spaceId, int $transactionId): TransactionCompletion
    {
        try {
            $this->logger->debug("Capturing transaction.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
            ]);

            $result = $this->completionGateway->capture($spaceId, $transactionId);

            $this->logger->debug("Transaction captured successfully.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
                'completionId' => $result->id,
                'state' => $result->state->value,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error("Capture failed.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw new TransactionException(
                "Unable to capture transaction $transactionId in space $spaceId: " . $e->getMessage(),
                new LocalizedString("Unable to capture transaction."),
                $e,
            );
        }
    }

    /**
     * Voids an authorized transaction.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID to void.
     * @return TransactionVoid The resulting void domain object.
     * @throws TransactionException If the void fails.
     */
    public function void(int $spaceId, int $transactionId): TransactionVoid
    {
        try {
            $this->logger->debug("Voiding transaction.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
            ]);

            $void = $this->completionGateway->void($spaceId, $transactionId);

            $this->logger->debug("Transaction voided successfully.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
                'state' => $void->state->value,
            ]);

            return $void;
        } catch (\Throwable $e) {
            $this->logger->error("Void failed.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw new TransactionException(
                "Unable to void transaction $transactionId in space $spaceId: " . $e->getMessage(),
                new LocalizedString("Unable to void transaction."),
                $e,
            );
        }
    }
}
