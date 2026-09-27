<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class FinishMonitorIn
{
    public function __construct(
        public readonly string $monitorId,
        public readonly string $reason,
    ) {
    }
}

final class FinishMonitorOut
{
    public function __construct(
        public readonly Monitor $monitor,
    ) {
    }
}

/**
 * The ingestion runner pauses a monitor whose source is gone (the live ended, the post disappeared),
 * so it does not stay "running" forever retrying. The reason stays visible until the next start.
 *
 * @template-implements UseCaseInterface<FinishMonitorIn, FinishMonitorOut>
 */
final class FinishMonitor implements UseCaseInterface
{
    public function __construct(
        private readonly MonitorRepositoryInterface $monitorRepository,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var FinishMonitorIn $input */
        $monitor = $this->monitorRepository->findById($input->monitorId);

        if ($monitor === null) {
            throw new NotFoundError('monitor.not_found');
        }

        if (!$monitor->isRunning()) {
            throw new ConflictError('monitor.not_running');
        }

        $reason = mb_substr(trim($input->reason), 0, 500);
        $finished = $monitor->clone([
            'status' => Monitor::STATUS_PAUSED,
            'runnerState' => null,
            'runnerDetail' => $reason !== '' ? $reason : null,
        ]);
        $finished->validate();

        $this->monitorRepository->update($finished);

        return new FinishMonitorOut($finished);
    }
}
