<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageRepositoryInterface;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class RemoveMonitorTagIn
{
    public function __construct(
        public readonly string $monitorId,
        public readonly string $userId,
        public readonly string $name,
    ) {
    }
}

final class RemoveMonitorTagOut
{
    public function __construct(
        public readonly Monitor $monitor,
        public readonly int $deletedMessages,
    ) {
    }
}

/**
 * Removing a tag also deletes the messages classified with it, so the monitor never shows messages
 * under a tag it no longer has. Only while paused, like any tag change.
 *
 * @template-implements UseCaseInterface<RemoveMonitorTagIn, RemoveMonitorTagOut>
 */
final class RemoveMonitorTag implements UseCaseInterface
{
    use LoadsOwnedMonitor;

    public function __construct(
        private readonly MonitorRepositoryInterface $monitorRepository,
        private readonly MessageRepositoryInterface $messageRepository,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var RemoveMonitorTagIn $input */
        $monitor = $this->loadOwnedMonitor($input->monitorId, $input->userId);
        $this->assertPaused($monitor);

        $removed = $monitor->findTag($input->name);
        if ($removed === null) {
            throw new NotFoundError('monitor.tag.not_found');
        }

        $tags = array_values(array_filter(
            $monitor->getTags(),
            static fn (MonitorTag $tag): bool => !$tag->isNamed($input->name),
        ));

        $updated = $monitor->clone(['tags' => $tags]);
        $updated->validate();

        $this->monitorRepository->update($updated);
        $deletedMessages = $this->messageRepository->deleteByLabel($monitor->getId(), $removed->name);

        return new RemoveMonitorTagOut($updated, $deletedMessages);
    }
}
