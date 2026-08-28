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
use PostFinanceCheckout\PluginCore\Sdk\SearchPaginationTrait;
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
    use SearchPaginationTrait;

    /**
     * @param SdkProvider $sdkProvider The SDK provider.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
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
            $service = $this->sdkProvider->getService(SdkManualTasksService::class);

            $query = sprintf('state:%s', $state->value);

            // Counted by walking the generator so that only one page is ever held
            // in memory, however many tasks the space has.
            return iterator_count($this->paginateSearch(
                static function (int $offset) use ($service, $spaceId, $query): object {
                    return $service->getManualTasksSearch(
                        $spaceId,
                        null,
                        SdkProvider::MAX_PAGE_SIZE,
                        $offset,
                        null,
                        $query,
                    );
                },
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to count manual tasks from SDK.', [
                'spaceId' => $spaceId,
                'state' => $state->value,
                'exception' => $e,
            ]);
            throw SdkProvider::wrapException(
                $e,
                ManualTaskException::class,
                'getManualTasksSearch',
                ['spaceId' => $spaceId, 'state' => $state->value],
                'Failed to retrieve manual tasks. Please try again or contact support.',
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function countAll(int $spaceId): int
    {
        try {
            /** @var SdkManualTasksService $service */
            $service = $this->sdkProvider->getService(SdkManualTasksService::class);

            // No query: the search then returns every manual task in the space.
            return iterator_count($this->paginateSearch(
                static function (int $offset) use ($service, $spaceId): object {
                    return $service->getManualTasksSearch(
                        $spaceId,
                        null,
                        SdkProvider::MAX_PAGE_SIZE,
                        $offset,
                    );
                },
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to count manual tasks from SDK.', [
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw SdkProvider::wrapException(
                $e,
                ManualTaskException::class,
                'getManualTasksSearch',
                ['spaceId' => $spaceId],
                'Failed to retrieve manual tasks. Please try again or contact support.',
            );
        }
    }

}
