<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV1;

use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Sdk\TokenMapperTrait;
use PostFinanceCheckout\PluginCore\Token\Exception\MissingTokenException;
use PostFinanceCheckout\PluginCore\Token\Exception\TokenException;
use PostFinanceCheckout\PluginCore\Token\Token;
use PostFinanceCheckout\PluginCore\Token\TokenGatewayInterface;
use PostFinanceCheckout\Sdk\Service\TokenService as SdkTokenService;

/**
 * SDK implementation of the TokenGatewayInterface for API V1.
 */
class TokenGateway implements TokenGatewayInterface
{
    use TokenMapperTrait;

    /**
     * @var SdkTokenService
     */
    private SdkTokenService $tokenService;

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
        $this->tokenService = $this->sdkProvider->getService(SdkTokenService::class);
    }

    /**
     * Attempts to create a token for a given transaction.
     *
     * Enforces fail-fast behavior: if the transaction does not support tokenization,
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
            'Attempting to create token for Transaction {transactionId} in Space {spaceId} (V1).',
            [
                'spaceId' => $spaceId,
                'transactionId' => $transactionId,
            ],
        );

        try {
            $sdkToken = $this->tokenService->createToken($spaceId, $transactionId);

            if ($sdkToken === null) {
                $this->logger->error(
                    'Token creation failed: SDK did not return a token. Transaction lacks the required tokenization state.',
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
                    'Failed to create token for transaction: {errorMessage}',
                    [
                        'errorMessage' => $e->getMessage(),
                        'exception' => $e,
                        'spaceId' => $spaceId,
                        'transactionId' => $transactionId,
                    ],
                );
                throw new TokenException(
                    "Failed to create token for transaction {$transactionId}: " . $e->getMessage(),
                    null,
                    0,
                    $e,
                );
            }
            throw $e;
        }
    }
}
