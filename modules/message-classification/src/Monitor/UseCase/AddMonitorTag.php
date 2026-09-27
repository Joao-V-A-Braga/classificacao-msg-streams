<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class AddMonitorTagIn
{
    public function __construct(
        public readonly string $monitorId,
        public readonly string $userId,
        public readonly string $name,
        public readonly ?string $description = null,
    ) {
    }
}

final class AddMonitorTagOut
{
    public function __construct(
        public readonly Monitor $monitor,
    ) {
    }
}

/**
 * @template-implements UseCaseInterface<AddMonitorTagIn, AddMonitorTagOut>
 */
final class AddMonitorTag implements UseCaseInterface
{
    use LoadsOwnedMonitor;

    public function __construct(
        private readonly MonitorRepositoryInterface $monitorRepository,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var AddMonitorTagIn $input */
        $monitor = $this->loadOwnedMonitor($input->monitorId, $input->userId);
        $this->assertPaused($monitor);

        $tag = new MonitorTag($input->name, $input->description);
        $tag->validate();

        if ($monitor->findTag($tag->name) !== null) {
            throw new ConflictError('monitor.tag.already_exists');
        }

        $updated = $monitor->clone(['tags' => [...$monitor->getTags(), $tag]]);
        $updated->validate();

        $this->monitorRepository->update($updated);

        return new AddMonitorTagOut($updated);
    }
}
