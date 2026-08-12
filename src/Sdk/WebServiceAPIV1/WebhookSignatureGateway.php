<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV1;

use PostFinanceCheckout\PluginCore\Log\DomainLoggerTrait;
use PostFinanceCheckout\PluginCore\Log\LogContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Webhook\Exception\WebhookSignatureValidationException;
use PostFinanceCheckout\PluginCore\Webhook\WebhookSignatureGatewayInterface;
use PostFinanceCheckout\Sdk\Service\WebhookEncryptionService as SdkWebhookEncryptionService;

/**
 * Class WebhookSignatureGateway
 *
 * Implementation of the WebhookSignatureGatewayInterface using the PostFinanceCheckout SDK.
 */
#[LogContext(domain: 'webhook')]
class WebhookSignatureGateway implements WebhookSignatureGatewayInterface
{
    use DomainLoggerTrait;
    /**
     * @var SdkWebhookEncryptionService
     */
    private SdkWebhookEncryptionService $webhookEncryptionService;

    /**
     * WebhookSignatureGateway constructor.
     *
     * @param SdkProvider $sdkProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
        $this->webhookEncryptionService = $this->sdkProvider->getService(SdkWebhookEncryptionService::class);
    }

    /**
     * Validates the payload signature.
     *
     * @param string $signatureHeader The signature string from the request headers.
     * @param string $payload The raw request body content.
     * @return bool True if the signature is valid, false otherwise.
     * @throws WebhookSignatureValidationException If signature validation fails due to key/API errors.
     */
    public function validate(string $signatureHeader, string $payload): bool
    {
        try {
            return (bool)$this->webhookEncryptionService->isContentValid($signatureHeader, $payload);
        } catch (\Throwable $e) {
            // TODO: Include spaceId and transactionId in log context when available
            $this->logger->error(
                'Webhook signature validation failed.',
                [
                    'errorMessage' => $e->getMessage(),
                    'exception' => $e,
                ],
            );
            throw SdkProvider::wrapException(
                $e,
                WebhookSignatureValidationException::class,
                'isContentValid',
                [],
                'Webhook signature validation failed.',
            );
        }
    }
}
