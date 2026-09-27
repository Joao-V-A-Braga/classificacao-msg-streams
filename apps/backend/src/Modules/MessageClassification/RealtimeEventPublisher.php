<?php

declare(strict_types=1);

namespace App\Modules\MessageClassification;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pushes events to the realtime hub (apps/realtime), which forwards them over WebSocket to every open
 * tab of the user. Best effort: a hub that is down never breaks the request that produced the event.
 */
final class RealtimeEventPublisher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
        #[\SensitiveParameter]
        private readonly string $apiKey,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function publish(string $userId, string $event, array $data): void
    {
        if ($this->baseUrl === '') {
            return;
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/publish', [
                'headers' => ['X-Realtime-Api-Key' => $this->apiKey],
                'json' => ['userId' => $userId, 'event' => $event, 'data' => $data],
                'timeout' => 2,
            ]);

            if ($response->getStatusCode() >= 300) {
                $this->logger->warning('Realtime hub answered {status} to {event}.', [
                    'status' => $response->getStatusCode(),
                    'event' => $event,
                ]);
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Could not publish {event} to the realtime hub: {message}', [
                'event' => $event,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
