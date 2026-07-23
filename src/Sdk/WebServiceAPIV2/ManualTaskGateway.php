<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV2;

use PostFinanceCheckout\PluginCore\Localization\LocalizedString;
use PostFinanceCheckout\PluginCore\Log\DomainLoggerTrait;
use PostFinanceCheckout\PluginCore\Log\LogContext;
use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\ManualTask\Exception\ManualTaskException;
use PostFinanceCheckout\PluginCore\ManualTask\ManualTaskGatewayInterface;
use PostFinanceCheckout\PluginCore\ManualTask\State;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\Sdk\Service\ManualTasksService as SdkManualTasksService;

/**
 * Gateway implementation using the SDK.
 *
 * Unlike WebServiceAPIV1, this SDK version has no direct count endpoint for
 * manual tasks — only a paginated search. {@see countByState()} pages through
 * the search results and sums them, so its cost scales with the number of
 * matching tasks (one HTTP call per 100 tasks) rather than being a single
 * cheap lookup.
 */
#[LogContext(domain: 'manual_task')]
class ManualTaskGateway implements ManualTaskGatewayInterface
{
    use DomainLoggerTrait;

    private const PAGE_SIZE = 100;

    /**
     * @param SdkProvider $provider The SDK provider.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly SdkProvider $provider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
    }

    /**
     * @inheritDoc
     */
    public function countByState(int $spaceId, State $state): int
    {
        try {
            /** @var SdkManualTasksService $service */
            $service = $this->provider->getService(SdkManualTasksService::class);

            $query = sprintf('state:%s', $state->value);
            $count = 0;
            $offset = 0;

            do {
                $page = $service->getManualTasksSearch(
                    $spaceId,
                    null,
                    self::PAGE_SIZE,
                    $offset,
                    null,
                    $query,
                );

                $count += count($page->getData());
                $offset += self::PAGE_SIZE;
            } while ($page->getHasMore());

            return $count;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to count manual tasks from SDK.', [
                'spaceId' => $spaceId,
                'state' => $state->value,
                'exception' => $e,
            ]);
            throw new ManualTaskException(
                sprintf('Failed to count manual tasks for space %d.', $spaceId),
                new LocalizedString('Failed to retrieve manual tasks. Please try again or contact support.'),
                $e,
            );
        }
    }
}
