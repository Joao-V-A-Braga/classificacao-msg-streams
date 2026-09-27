<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class CreateMonitorIn
{
    /**
     * @param array<int, array{name?: mixed, description?: mixed}> $tags
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $name,
        public readonly string $sourceType,
        public readonly string $url,
        public readonly array $tags = [],
        public readonly bool $includeExistingComments = false,
        public readonly bool $translateForLaya = true,
    ) {
    }
}

final class CreateMonitorOut
{
    public function __construct(
        public readonly Monitor $monitor,
    ) {
    }
}

/**
 * Creates a monitor paused: it only starts watching when the user starts it.
 *
 * @template-implements UseCaseInterface<CreateMonitorIn, CreateMonitorOut>
 */
final class CreateMonitor implements UseCaseInterface
{
    public function __construct(
        private readonly MonitorRepositoryInterface $monitorRepository,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var CreateMonitorIn $input */
        $monitor = new Monitor([
            'userId' => $input->userId,
            'name' => trim($input->name),
            'sourceType' => $input->sourceType,
            'url' => trim($input->url),
            'status' => Monitor::STATUS_PAUSED,
            'tags' => array_map(MonitorTag::fromArray(...), array_values($input->tags)),
            'includeExistingComments' => $input->includeExistingComments,
            'translateForLaya' => $input->translateForLaya,
        ]);
        $monitor->validate();

        $this->monitorRepository->create($monitor);

        return new CreateMonitorOut($monitor);
    }
}
