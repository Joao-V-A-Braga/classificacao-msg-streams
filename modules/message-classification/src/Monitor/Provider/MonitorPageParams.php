<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\Provider;

final class MonitorPageParams
{
    public function __construct(
        public readonly string $userId,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }
}
