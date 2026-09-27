<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageRepositoryInterface;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class DeleteMonitorIn
{
    public function __construct(
        public readonly string $monitorId,
        public readonly string $userId,
    ) {
    }
}

final class DeleteMonitorOut
{
    public function __construct(
        public readonly Monitor $monitor,
    ) {
    }
}

/**
 * Deletes the monitor and every message it collected. A running monitor is paused first, then
 * deleted; the runner notices it is gone and closes its tab.
 *
 * @template-implements UseCaseInterface<DeleteMonitorIn, DeleteMonitorOut>
 */
final class DeleteMonitor implements UseCaseInterface
{
    use LoadsOwnedMonitor;

    public function __construct(
        private readonly MonitorRepositoryInterface $monitorRepository,
        private readonly MessageRepositoryInterface $messageRepository,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var DeleteMonitorIn $input */
        $monitor = $this->loadOwnedMonitor($input->monitorId, $input->userId);

        if ($monitor->isRunning()) {
            // Stop it first: from here on the ingestion rejects its messages and the runner closes
            // its tab, so nothing is written for a monitor that is being deleted.
            $monitor = $monitor->clone(['status' => Monitor::STATUS_PAUSED, 'runnerState' => null, 'runnerDetail' => null]);
            $monitor->validate();
            $this->monitorRepository->update($monitor);
        }

        $this->messageRepository->deleteByMonitorId($monitor->getId());
        $this->monitorRepository->delete($monitor->getId());

        return new DeleteMonitorOut($monitor);
    }
}
