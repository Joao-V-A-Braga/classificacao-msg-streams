<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class StartMonitorIn
{
    public function __construct(
        public readonly string $monitorId,
        public readonly string $userId,
    ) {
    }
}

final class StartMonitorOut
{
    public function __construct(
        public readonly Monitor $monitor,
    ) {
    }
}

/**
 * Every start is a fresh observation: `startedAt` moves to now and the runner only ingests the
 * messages that show up from then on (whatever was already in the chat/post is ignored).
 *
 * @template-implements UseCaseInterface<StartMonitorIn, StartMonitorOut>
 */
final class StartMonitor implements UseCaseInterface
{
    use LoadsOwnedMonitor;

    public function __construct(
        private readonly MonitorRepositoryInterface $monitorRepository,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var StartMonitorIn $input */
        $monitor = $this->loadOwnedMonitor($input->monitorId, $input->userId);

        if ($monitor->isRunning()) {
            throw new ConflictError('monitor.already_running');
        }

        if (count($monitor->getTags()) < Monitor::MIN_TAGS_TO_START) {
            throw new ConflictError('monitor.tags.not_enough');
        }

        $started = $monitor->clone([
            'status' => Monitor::STATUS_RUNNING,
            'startedAt' => new \DateTimeImmutable(),
            'runnerState' => 'starting',
            'runnerDetail' => null,
        ]);
        $started->validate();

        $this->monitorRepository->update($started);

        return new StartMonitorOut($started);
    }
}
