<?php

declare(strict_types=1);

namespace App\Tests\Modules\MessageClassification;

use App\Modules\MessageClassification\IngestionRunnerHeartbeat;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class IngestionRunnerHeartbeatTest extends TestCase
{
    public function testIsOfflineUntilTheRunnerPolls(): void
    {
        $heartbeat = new IngestionRunnerHeartbeat(new ArrayAdapter());

        self::assertNull($heartbeat->lastSeenAt());
        self::assertFalse($heartbeat->isOnline());
    }

    public function testIsOnlineRightAfterAPollAndOfflineWhenItGetsStale(): void
    {
        $heartbeat = new IngestionRunnerHeartbeat(new ArrayAdapter());
        $polledAt = new \DateTimeImmutable('2026-09-26T23:00:00+00:00');

        $heartbeat->beat($polledAt);

        self::assertSame($polledAt->getTimestamp(), $heartbeat->lastSeenAt()?->getTimestamp());
        self::assertTrue($heartbeat->isOnline($polledAt->modify('+10 seconds')));
        self::assertFalse($heartbeat->isOnline($polledAt->modify('+1 minute')));
    }
}
