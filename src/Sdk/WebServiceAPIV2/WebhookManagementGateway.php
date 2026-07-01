<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Sdk\WebServiceAPIV2;

use PostFinanceCheckout\PluginCore\Log\LoggerInterface;
use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Webhook\Enum\WebhookListener as WebhookListenerEnum;
use PostFinanceCheckout\PluginCore\Webhook\WebhookListener;
use PostFinanceCheckout\PluginCore\Webhook\WebhookListenerCollection;
use PostFinanceCheckout\PluginCore\Webhook\WebhookManagementGatewayInterface;
use PostFinanceCheckout\PluginCore\Webhook\WebhookUrl;
use PostFinanceCheckout\PluginCore\Webhook\WebhookUrlCollection;
use PostFinanceCheckout\Sdk\Model\CreationEntityState as SdkCreationEntityState;
use PostFinanceCheckout\Sdk\Model\WebhookListenerCreate as SdkWebhookListenerCreate;
use PostFinanceCheckout\Sdk\Model\WebhookListenerUpdate as SdkWebhookListenerUpdate;
use PostFinanceCheckout\Sdk\Model\WebhookUrlCreate as SdkWebhookUrlCreate;
use PostFinanceCheckout\Sdk\Model\WebhookUrlUpdate as SdkWebhookUrlUpdate;
use PostFinanceCheckout\Sdk\Service\WebhookListenersService as SdkWebhookListenersService;
use PostFinanceCheckout\Sdk\Service\WebhookURLsService as SdkWebhookURLsService;

/**
 * Class WebhookManagementGateway
 *
 * Implementation of the WebhookManagementGatewayInterface using the PostFinanceCheckout SDK V2.
 */
class WebhookManagementGateway implements WebhookManagementGatewayInterface
{
    private SdkWebhookURLsService $webhookUrlService;
    private SdkWebhookListenersService $webhookListenerService;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
        private readonly LoggerInterface $logger,
    ) {
        $this->webhookUrlService = $this->sdkProvider->getService(SdkWebhookURLsService::class);
        $this->webhookListenerService = $this->sdkProvider->getService(SdkWebhookListenersService::class);
    }

    /**
     * Maps an SDK webhook listener to the domain WebhookListener DTO.
     *
     * @param mixed $sdkListener The SDK webhook listener object.
     * @return WebhookListener The domain listener.
     */
    private function mapToWebhookListener($sdkListener): WebhookListener
    {
        return new WebhookListener(
            (int) $sdkListener->getId(),
            (string) $sdkListener->getName(),
            (int) $sdkListener->getEntity(),
            $sdkListener->getEntityStates() ?? [],
        );
    }

    /**
     * Maps an SDK webhook URL to the domain WebhookUrl DTO.
     *
     * @param mixed $sdkUrl The SDK webhook URL object.
     * @return WebhookUrl The domain URL.
     */
    private function mapToWebhookUrl($sdkUrl): WebhookUrl
    {
        return new WebhookUrl(
            (int) $sdkUrl->getId(),
            (string) $sdkUrl->getName(),
            (string) $sdkUrl->getUrl(),
            (int) $sdkUrl->getState(),
        );
    }

    public function createListener(
        int $spaceId,
        int $webhookUrlId,
        WebhookListenerEnum $entity,
        array $eventStates,
        string $name,
        bool $notifyEveryChange = false,
    ): WebhookListener {
        $this->logger->debug("Creating Webhook Listener.", [
            'spaceId' => $spaceId,
            'webhookUrlId' => $webhookUrlId,
            'entity' => $entity->value,
            'name' => $name,
        ]);

        $sdkEntity = new SdkWebhookListenerCreate();
        $sdkEntity->setName($name);
        $sdkEntity->setUrl($webhookUrlId);
        $sdkEntity->setEntity($entity->value);
        $sdkEntity->setEntityStates($eventStates);
        $sdkEntity->setState(SdkCreationEntityState::ACTIVE);
        $sdkEntity->setNotifyEveryChange($notifyEveryChange);

        // V2: postWebhooksListeners returns the fully hydrated listener entity.
        $result = $this->webhookListenerService->postWebhooksListeners($spaceId, $sdkEntity);
        return $this->mapToWebhookListener($result);
    }

    public function createUrl(int $spaceId, string $url, string $name): WebhookUrl
    {
        $this->logger->debug("Creating Webhook URL config.", [
            'spaceId' => $spaceId,
            'name' => $name,
            'url' => $url,
        ]);

        $entity = new SdkWebhookUrlCreate();
        $entity->setUrl($url);
        $entity->setName($name);
        $entity->setState(SdkCreationEntityState::ACTIVE);

        // V2: postWebhooksUrls returns the fully hydrated URL entity.
        $result = $this->webhookUrlService->postWebhooksUrls($spaceId, $entity);

        return $this->mapToWebhookUrl($result);
    }

    public function deleteListener(int $spaceId, int $listenerId): void
    {
        $this->logger->debug("Deleting Webhook Listener.", [
            'listenerId' => $listenerId,
            'spaceId' => $spaceId,
        ]);
        $this->webhookListenerService->deleteWebhooksListenersId($listenerId, $spaceId);
    }

    public function deleteUrl(int $spaceId, int $webhookUrlId): void
    {
        $this->logger->debug("Deleting Webhook URL.", [
            'webhookUrlId' => $webhookUrlId,
            'spaceId' => $spaceId,
        ]);
        $this->webhookUrlService->deleteWebhooksUrlsId($webhookUrlId, $spaceId);
    }

    public function getUrl(int $spaceId, int $webhookUrlId): WebhookUrl
    {
        $this->logger->debug("Getting Webhook URL.", [
            'webhookUrlId' => $webhookUrlId,
            'spaceId' => $spaceId,
        ]);
        $sdkUrl = $this->webhookUrlService->getWebhooksUrlsId($webhookUrlId, $spaceId);

        return $this->mapToWebhookUrl($sdkUrl);
    }

    public function getWebhookListeners(int $spaceId, int $urlId): WebhookListenerCollection
    {
        $this->logger->debug("Getting Webhook Listeners for URL.", [
            'urlId' => $urlId,
            'spaceId' => $spaceId,
        ]);

        // V2 Search: query string
        $query = "url.id:$urlId";
        $results = $this->webhookListenerService->getWebhooksListenersSearch($spaceId, null, 100, null, null, $query);
        $data = (is_object($results) && method_exists($results, 'getData')) ? $results->getData() : (array)$results;

        return new WebhookListenerCollection(...array_map([$this, 'mapToWebhookListener'], $data));
    }

    public function getWebhookUrls(int $spaceId, ?string $state = 'ACTIVE'): WebhookUrlCollection
    {
        $this->logger->debug("Getting Webhook URLs.", [
            'spaceId' => $spaceId,
            'state' => $state,
        ]);

        if ($state !== null) {
            // Filter is applied server-side via API search query.
            $results = $this->webhookUrlService->getWebhooksUrlsSearch(
                $spaceId,
                null,              // expand
                100,               // limit (API maximum)
                null,              // offset
                null,              // order
                "state:$state",    // server-side state filter
            );
        } else {
            // No state filter — use the plain list endpoint.
            $results = $this->webhookUrlService->getWebhooksUrls($spaceId, null, null, null, 100, null);
        }

        $data = (is_object($results) && method_exists($results, 'getData'))
            ? $results->getData()
            : (array) $results;

        return new WebhookUrlCollection(...array_map([$this, 'mapToWebhookUrl'], $data));
    }

    public function listListeners(int $spaceId): WebhookListenerCollection
    {
        $this->logger->debug("Listing Webhook Listeners.", ['spaceId' => $spaceId]);
        $results = $this->webhookListenerService->getWebhooksListeners($spaceId, null, null, null, 100, null);
        $data = (is_object($results) && method_exists($results, 'getData')) ? $results->getData() : (array)$results;

        return new WebhookListenerCollection(...array_map([$this, 'mapToWebhookListener'], $data));
    }

    public function listUrls(int $spaceId): WebhookUrlCollection
    {
        $this->logger->debug("Listing Webhook URLs.", ['spaceId' => $spaceId]);
        // V2 Search: using generic query or empty for all.
        // Use the standard Webhook URL retrieval method.
        $results = $this->webhookUrlService->getWebhooksUrls($spaceId, null, null, null, 100, null);
        $data = (is_object($results) && method_exists($results, 'getData')) ? $results->getData() : (array)$results;

        return new WebhookUrlCollection(...array_map([$this, 'mapToWebhookUrl'], $data));
    }

    public function updateListener(int $spaceId, int $listenerId, WebhookListenerEnum $entity, array $eventStates): WebhookListener
    {
        $this->logger->debug("Updating Webhook Listener.", [
            'listenerId' => $listenerId,
            'spaceId' => $spaceId,
            'entity' => $entity->value,
        ]);

        $currentListener = $this->webhookListenerService->getWebhooksListenersId($listenerId, $spaceId);

        $update = new SdkWebhookListenerUpdate();
        $update->setVersion($currentListener->getVersion());
        $update->setEntityStates($eventStates);

        // patchWebhooksListenersId returns the fully hydrated, updated listener entity.
        $result = $this->webhookListenerService->patchWebhooksListenersId($listenerId, $spaceId, $update);

        return $this->mapToWebhookListener($result);
    }

    public function updateUrl(int $spaceId, int $webhookUrlId, string $newUrl): WebhookUrl
    {
        $this->logger->debug("Updating Webhook URL.", [
            'webhookUrlId' => $webhookUrlId,
            'spaceId' => $spaceId,
            'newUrl' => $newUrl,
        ]);

        $currentUrl = $this->webhookUrlService->getWebhooksUrlsId($webhookUrlId, $spaceId);

        $update = new SdkWebhookUrlUpdate();
        $update->setVersion($currentUrl->getVersion());
        $update->setName($currentUrl->getName());
        $update->setState($currentUrl->getState());
        $update->setUrl($newUrl);

        // patchWebhooksUrlsId returns the fully hydrated, updated URL entity.
        $result = $this->webhookUrlService->patchWebhooksUrlsId($webhookUrlId, $spaceId, $update);

        return $this->mapToWebhookUrl($result);
    }
}
