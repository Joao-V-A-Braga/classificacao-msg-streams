<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class PauseMonitorIn
{
    public function __construct(
        public readonly string $monitorId,
        public readonly string $userId,
    ) {
    }
}

final class PauseMonitorOut
{
    public function __construct(
        public readonly Monitor $monitor,
    ) {
    }
}

/**
 * @template-implements UseCaseInterface<PauseMonitorIn, PauseMonitorOut>
 */
final class PauseMonitor implements UseCaseInterface
{
    use LoadsOwnedMonitor;

    public function __construct(
        private readonly MonitorRepositoryInterface $monitorRepository,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var PauseMonitorIn $input */
        $monitor = $this->loadOwnedMonitor($input->monitorId, $input->userId);

        if (!$monitor->isRunning()) {
            throw new ConflictError('monitor.not_running');
        }

        $paused = $monitor->clone([
            'status' => Monitor::STATUS_PAUSED,
            'runnerState' => null,
            'runnerDetail' => null,
        ]);
        $paused->validate();

        $this->monitorRepository->update($paused);

        return new PauseMonitorOut($paused);
    }
}
