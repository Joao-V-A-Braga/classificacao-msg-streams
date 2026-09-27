<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;

/**
 * Shared by the monitor commands a user runs on their own monitors: 404 when it does not exist,
 * 403 when it belongs to someone else.
 *
 * @property-read MonitorRepositoryInterface $monitorRepository
 */
trait LoadsOwnedMonitor
{
    private function loadOwnedMonitor(string $monitorId, string $userId): Monitor
    {
        $monitor = $this->monitorRepository->findById($monitorId);

        if ($monitor === null) {
            throw new NotFoundError('monitor.not_found');
        }

        $monitor->assertOwnedBy($userId);

        return $monitor;
    }

    /**
     * Tags are only edited while the monitor is paused, so a running pipeline never changes its
     * classification options halfway.
     */
    private function assertPaused(Monitor $monitor): void
    {
        if ($monitor->isRunning()) {
            throw new ConflictError('monitor.must_be_paused');
        }
    }
}
