<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Mock;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;

final class MonitorFixture
{
    public const USER_ID = '6f1c2f8e-5b0a-4c1e-9d3a-7b2e8f4a1c90';

    public const OTHER_USER_ID = '0a9b8c7d-6e5f-4a3b-8c1d-2e3f4a5b6c7d';

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function props(array $overrides = []): array
    {
        return array_merge([
            'userId' => self::USER_ID,
            'name' => 'Live de lançamento',
            'sourceType' => Monitor::SOURCE_YOUTUBE_LIVE,
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'status' => Monitor::STATUS_PAUSED,
            'tags' => [
                new MonitorTag('pergunta', 'a question asking for information'),
                new MonitorTag('elogio', 'praise or a compliment'),
                new MonitorTag('outro'),
            ],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function monitor(array $overrides = []): Monitor
    {
        return new Monitor(self::props($overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function running(array $overrides = []): Monitor
    {
        return self::monitor(array_merge([
            'status' => Monitor::STATUS_RUNNING,
            'startedAt' => new \DateTimeImmutable('2026-09-26T10:00:00+00:00'),
            'runnerState' => 'watching',
        ], $overrides));
    }
}
