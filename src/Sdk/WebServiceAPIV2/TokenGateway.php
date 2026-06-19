<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV2;

use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Sdk\TokenMapperTrait;
use PostFinanceCheckout\PluginCore\Token\Exception\MissingTokenException;
use PostFinanceCheckout\PluginCore\Token\Exception\TokenException;
use PostFinanceCheckout\PluginCore\Token\Token;
use PostFinanceCheckout\PluginCore\Token\TokenGatewayInterface;
use PostFinanceCheckout\Sdk\Model\Token as SdkToken;
use PostFinanceCheckout\Sdk\Service\TransactionsService as SdkTransactionsService;

/**
 * SDK implementation of the TokenGatewayInterface for API V2.
 */
class TokenGateway implements TokenGatewayInterface
{
    use TokenMapperTrait;

    /**
     * @var SdkTransactionsService
     */
    private SdkTransactionsService $transactionsService;

    /**
     * Constructs the TokenGateway instance.
     *
     * @param SdkProvider $sdkProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
        private readonly LoggerInterface $logger,
    ) {
        $this->transactionsService = $this->sdkProvider->getService(SdkTransactionsService::class);
    }

    /**
     * Attempts to retrieve or map a token for a given transaction.
     *
     * Enforces fail-fast behavior: if the transaction does not have an associated token,
     * it throws MissingTokenException.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return Token
     * @throws MissingTokenException
     * @throws TokenException
     */
    public function createToken(int $spaceId, int $transactionId): Token
    {
        $this->logger->debug(
            'Creating/Fetching Token for Transaction {transactionId} in Space {spaceId} (V2).',
            [
                'spaceId' => $spaceId,
                'transactionId' => $transactionId,
            ],
        );

        try {
            $transaction = $this->transactionsService->getPaymentTransactionsId($transactionId, $spaceId, ['token']);
            $sdkToken = $transaction->getToken();

            if ($sdkToken === null) {
                $this->logger->error(
                    'Token creation failed: Transaction does not have an associated token. The original transaction must have been created with tokenizationMode = FORCE_CREATION.',
                    [
                        'spaceId' => $spaceId,
                        'transactionId' => $transactionId,
                    ],
                );
                throw new MissingTokenException(
                    "Transaction {$transactionId} in Space {$spaceId} has no associated token.",
                );
            }

            return $this->mapToToken($sdkToken, $spaceId);
        } catch (\Exception $e) {
            if (!($e instanceof MissingTokenException)) {
                $this->logger->error(
                    'Failed to fetch token for transaction: {errorMessage}',
                    [
                        'errorMessage' => $e->getMessage(),
                        'exception' => $e,
                        'spaceId' => $spaceId,
                        'transactionId' => $transactionId,
                    ],
                );
                throw new TokenException(
                    "Failed to fetch token for transaction {$transactionId}: " . $e->getMessage(),
                    null,
                    0,
                    $e,
                );
            }
            throw $e;
        }
    }
}
