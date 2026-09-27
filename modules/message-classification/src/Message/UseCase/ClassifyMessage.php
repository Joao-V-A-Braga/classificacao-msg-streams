<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Message\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageClassifierProviderInterface;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageRepositoryInterface;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class ClassifyMessageIn
{
    public function __construct(
        public readonly string $messageId,
    ) {
    }
}

final class ClassifyMessageOut
{
    public function __construct(
        public readonly Message $message,
    ) {
    }
}

/**
 * Classifies a message with the current tags of its monitor.
 *
 * @template-implements UseCaseInterface<ClassifyMessageIn, ClassifyMessageOut>
 */
final class ClassifyMessage implements UseCaseInterface
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly MonitorRepositoryInterface $monitorRepository,
        private readonly MessageClassifierProviderInterface $messageClassifier,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var ClassifyMessageIn $input */
        $message = $this->messageRepository->findById($input->messageId);

        if ($message === null) {
            throw new NotFoundError('message.not_found');
        }

        $monitor = $this->monitorRepository->findById($message->getMonitorId());

        if ($monitor === null) {
            throw new NotFoundError('monitor.not_found');
        }

        if ($monitor->getTags() === []) {
            throw new ConflictError('monitor.tags.not_enough');
        }

        $classification = $this->messageClassifier->classify($message->getText(), $monitor->getTags(), $monitor->translatesForLaya());
        $tag = $monitor->findTag($classification->label);

        if ($tag === null) {
            throw new \UnexpectedValueException(sprintf('Classifier returned a label that is not a tag of the monitor: %s', $classification->label));
        }

        $classifiedMessage = $message->clone([
            'label' => $tag->name,
            'labelProbabilities' => $classification->probabilities,
            'translatedText' => $classification->translatedText,
            'classifiedAt' => new \DateTimeImmutable(),
        ]);
        $classifiedMessage->validate();

        $this->messageRepository->update($classifiedMessage);

        return new ClassifyMessageOut($classifiedMessage);
    }
}
