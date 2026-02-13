<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\SdkV1;

use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Transaction\TransactionComment;
use PostFinanceCheckout\PluginCore\Transaction\TransactionCommentGatewayInterface;
use PostFinanceCheckout\Sdk\Model\TransactionComment as SdkTransactionComment;
use PostFinanceCheckout\Sdk\Service\TransactionCommentService as SdkTransactionCommentService;

class TransactionCommentGateway implements TransactionCommentGatewayInterface
{
    private SdkTransactionCommentService $service;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
        private readonly LoggerInterface $logger,
    ) {
        $this->service = $this->sdkProvider->getService(SdkTransactionCommentService::class);
    }

    /**
     * @inheritDoc
     */
    public function getComments(int $spaceId, int $transactionId): array
    {
        try {
            $this->logger->debug("Fetching comments for Transaction $transactionId in Space $spaceId.");
            $sdkComments = $this->service->all($spaceId, $transactionId);

            return array_map([$this, 'mapToTransactionComment'], $sdkComments);
        } catch (\Exception $e) {
            $this->logger->error("Failed to fetch transaction comments: " . $e->getMessage());
            return [];
        }
    }

    private function mapToTransactionComment(SdkTransactionComment $sdkComment): TransactionComment
    {
        $comment = new TransactionComment();
        $comment->id = $sdkComment->getId();
        $comment->content = $sdkComment->getContent();

        $createdOn = $sdkComment->getCreatedOn();
        if ($createdOn) {
            $comment->createdOn = \DateTimeImmutable::createFromMutable($createdOn);
        }

        return $comment;
    }
}
