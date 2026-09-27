<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class ConfigureMonitorIn
{
    /**
     * A null setting is left as it is.
     */
    public function __construct(
        public readonly string $monitorId,
        public readonly string $userId,
        public readonly ?bool $includeExistingComments = null,
        public readonly ?bool $translateForLaya = null,
    ) {
    }
}

final class ConfigureMonitorOut
{
    public function __construct(
        public readonly Monitor $monitor,
    ) {
    }
}

/**
 * Changes how the monitor reads and classifies its source.
 *
 * - includeExistingComments: only while paused, like the tags (it is applied on the next start).
 * - translateForLaya: any time; it applies from the next message on.
 *
 * @template-implements UseCaseInterface<ConfigureMonitorIn, ConfigureMonitorOut>
 */
final class ConfigureMonitor implements UseCaseInterface
{
    use LoadsOwnedMonitor;

    public function __construct(
        private readonly MonitorRepositoryInterface $monitorRepository,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var ConfigureMonitorIn $input */
        $monitor = $this->loadOwnedMonitor($input->monitorId, $input->userId);
        $changes = [];

        if ($input->includeExistingComments !== null && $input->includeExistingComments !== $monitor->includesExistingComments()) {
            $this->assertPaused($monitor);
            $changes['includeExistingComments'] = $input->includeExistingComments;
        }

        if ($input->translateForLaya !== null) {
            $changes['translateForLaya'] = $input->translateForLaya;
        }

        if ($changes === []) {
            return new ConfigureMonitorOut($monitor);
        }

        $updated = $monitor->clone($changes);
        $updated->validate();

        $this->monitorRepository->update($updated);

        return new ConfigureMonitorOut($updated);
    }
}
