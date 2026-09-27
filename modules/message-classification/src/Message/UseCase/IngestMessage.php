<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Message\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageRepositoryInterface;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class IngestMessageIn
{
    public function __construct(
        public readonly string $monitorId,
        public readonly string $authorName,
        public readonly string $text,
        public readonly \DateTimeImmutable $receivedAt,
        public readonly ?string $externalId = null,
    ) {
    }
}

final class IngestMessageOut
{
    /**
     * @param bool $created false when the message had already been ingested (same externalId)
     */
    public function __construct(
        public readonly Message $message,
        public readonly Monitor $monitor,
        public readonly bool $created,
    ) {
    }
}

/**
 * Only a running monitor accepts messages; a paused or deleted one tells the runner to stop.
 *
 * @template-implements UseCaseInterface<IngestMessageIn, IngestMessageOut>
 */
final class IngestMessage implements UseCaseInterface
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly MonitorRepositoryInterface $monitorRepository,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var IngestMessageIn $input */
        $monitor = $this->monitorRepository->findById($input->monitorId);

        if ($monitor === null) {
            throw new NotFoundError('monitor.not_found');
        }

        if (!$monitor->isRunning()) {
            throw new ConflictError('monitor.not_running');
        }

        $externalId = $input->externalId !== null && trim($input->externalId) !== '' ? trim($input->externalId) : null;
        if ($externalId !== null) {
            $existing = $this->messageRepository->findByExternalId($monitor->getId(), $externalId);
            if ($existing !== null) {
                return new IngestMessageOut($existing, $monitor, false);
            }
        }

        $message = new Message([
            'monitorId' => $monitor->getId(),
            'externalId' => $externalId,
            'authorName' => trim($input->authorName),
            'text' => trim($input->text),
            'receivedAt' => $input->receivedAt,
        ]);
        $message->validate();

        $this->messageRepository->create($message);

        return new IngestMessageOut($message, $monitor, true);
    }
}
