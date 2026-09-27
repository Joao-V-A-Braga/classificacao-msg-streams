<?php

declare(strict_types=1);

namespace App\Modules\MessageClassification;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Last time the ingestion runner polled GET /ingestion/monitors. The runner polls every few seconds,
 * so a stale heartbeat means the ingestion service is not running and started monitors stay waiting.
 */
final class IngestionRunnerHeartbeat
{
    private const KEY = 'ingestion_runner_last_seen';

    /** Runner polls every 3 s by default; tolerate a few missed polls (slow Laya load, restarts). */
    public const ONLINE_WINDOW_SECONDS = 20;

    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function beat(?\DateTimeImmutable $now = null): void
    {
        $item = $this->cache->getItem(self::KEY);
        $item->set(($now ?? new \DateTimeImmutable())->getTimestamp());
        $this->cache->save($item);
    }

    public function lastSeenAt(): ?\DateTimeImmutable
    {
        $item = $this->cache->getItem(self::KEY);

        return $item->isHit() ? (new \DateTimeImmutable())->setTimestamp((int) $item->get()) : null;
    }

    public function isOnline(?\DateTimeImmutable $now = null): bool
    {
        $lastSeenAt = $this->lastSeenAt();
        $now ??= new \DateTimeImmutable();

        return $lastSeenAt !== null && $now->getTimestamp() - $lastSeenAt->getTimestamp() <= self::ONLINE_WINDOW_SECONDS;
    }
}
