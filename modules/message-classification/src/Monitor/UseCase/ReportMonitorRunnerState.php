<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class ReportMonitorRunnerStateIn
{
    public function __construct(
        public readonly string $monitorId,
        public readonly string $state,
        public readonly ?string $detail = null,
    ) {
    }
}

final class ReportMonitorRunnerStateOut
{
    public function __construct(
        public readonly Monitor $monitor,
    ) {
    }
}

/**
 * The ingestion runner tells how the watching is going (opening the page, watching, failing).
 * A paused monitor answers 409 so the runner stops watching it.
 *
 * @template-implements UseCaseInterface<ReportMonitorRunnerStateIn, ReportMonitorRunnerStateOut>
 */
final class ReportMonitorRunnerState implements UseCaseInterface
{
    public function __construct(
        private readonly MonitorRepositoryInterface $monitorRepository,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var ReportMonitorRunnerStateIn $input */
        $monitor = $this->monitorRepository->findById($input->monitorId);

        if ($monitor === null) {
            throw new NotFoundError('monitor.not_found');
        }

        if (!$monitor->isRunning()) {
            throw new ConflictError('monitor.not_running');
        }

        $detail = $input->detail !== null ? mb_substr(trim($input->detail), 0, 500) : null;
        $updated = $monitor->clone([
            'runnerState' => $input->state,
            'runnerDetail' => $detail === '' ? null : $detail,
        ]);
        $updated->validate();

        $this->monitorRepository->update($updated);

        return new ReportMonitorRunnerStateOut($updated);
    }
}
