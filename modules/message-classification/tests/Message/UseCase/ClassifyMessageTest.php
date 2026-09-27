<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Message\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;
use ClassificacaoMsgStreams\MessageClassification\Message\UseCase\ClassifyMessage;
use ClassificacaoMsgStreams\MessageClassification\Message\UseCase\ClassifyMessageIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMessageClassifierProvider;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMessageRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMonitorRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\MonitorFixture;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use PHPUnit\Framework\TestCase;

final class ClassifyMessageTest extends TestCase
{
    private FakeMessageRepository $messageRepository;

    private FakeMonitorRepository $monitorRepository;

    protected function setUp(): void
    {
        $this->messageRepository = new FakeMessageRepository();
        $this->monitorRepository = new FakeMonitorRepository();
    }

    private function storeMessage(Monitor $monitor): Message
    {
        return $this->messageRepository->create(new Message([
            'monitorId' => $monitor->getId(),
            'authorName' => 'viewer123',
            'text' => 'Quanto custa?',
            'receivedAt' => new \DateTimeImmutable(),
        ]));
    }

    public function testClassifiesWithTheMonitorTagsAndPersistsTheLabel(): void
    {
        $monitor = $this->monitorRepository->create(MonitorFixture::running());
        $message = $this->storeMessage($monitor);
        $classifier = new FakeMessageClassifierProvider('pergunta', ['pergunta' => 0.8, 'elogio' => 0.1, 'outro' => 0.1]);

        $result = (new ClassifyMessage($this->messageRepository, $this->monitorRepository, $classifier))
            ->execute(new ClassifyMessageIn($message->getId()));

        self::assertSame('pergunta', $result->message->getLabel());
        self::assertSame(['pergunta' => 0.8, 'elogio' => 0.1, 'outro' => 0.1], $result->message->getLabelProbabilities());
        self::assertNotNull($result->message->getClassifiedAt());
        self::assertSame('pergunta', $this->messageRepository->findById($message->getId())->getLabel());
        self::assertSame('Quanto custa?', $classifier->lastText);
        self::assertSame(['pergunta', 'elogio', 'outro'], array_map(static fn ($tag) => $tag->name, $classifier->lastTags));
    }

    public function testTellsTheClassifierWhetherToTranslate(): void
    {
        $monitor = $this->monitorRepository->create(MonitorFixture::running(['translateForLaya' => false]));
        $message = $this->storeMessage($monitor);
        $classifier = new FakeMessageClassifierProvider('pergunta');

        (new ClassifyMessage($this->messageRepository, $this->monitorRepository, $classifier))
            ->execute(new ClassifyMessageIn($message->getId()));

        self::assertFalse($classifier->lastTranslate);
    }

    public function testStoresTheEnglishVersionTheClassifierRead(): void
    {
        $monitor = $this->monitorRepository->create(MonitorFixture::running());
        $message = $this->storeMessage($monitor);
        $classifier = new FakeMessageClassifierProvider('pergunta', ['pergunta' => 1.0], 'How much is it?');

        $result = (new ClassifyMessage($this->messageRepository, $this->monitorRepository, $classifier))
            ->execute(new ClassifyMessageIn($message->getId()));

        self::assertSame('How much is it?', $result->message->getTranslatedText());
        self::assertSame('Quanto custa?', $result->message->getText());
    }

    public function testStoresTheTagNameAsWrittenByTheUser(): void
    {
        $monitor = $this->monitorRepository->create(MonitorFixture::running());
        $message = $this->storeMessage($monitor);

        $result = (new ClassifyMessage($this->messageRepository, $this->monitorRepository, new FakeMessageClassifierProvider('PERGUNTA')))
            ->execute(new ClassifyMessageIn($message->getId()));

        self::assertSame('pergunta', $result->message->getLabel());
    }

    public function testFailsWhenTheClassifierAnswersSomethingThatIsNotATag(): void
    {
        $monitor = $this->monitorRepository->create(MonitorFixture::running());
        $message = $this->storeMessage($monitor);

        $this->expectException(\UnexpectedValueException::class);

        (new ClassifyMessage($this->messageRepository, $this->monitorRepository, new FakeMessageClassifierProvider('spam')))
            ->execute(new ClassifyMessageIn($message->getId()));
    }

    public function testThrowsNotFoundWithoutCallingTheClassifier(): void
    {
        $classifier = new FakeMessageClassifierProvider();

        try {
            (new ClassifyMessage($this->messageRepository, $this->monitorRepository, $classifier))
                ->execute(new ClassifyMessageIn('3d6f1b2a-9c8e-4f7d-a1b2-c3d4e5f6a7b8'));
            self::fail('Expected NotFoundError');
        } catch (NotFoundError $error) {
            self::assertSame('message.not_found', $error->getMessage());
        }

        self::assertSame(0, $classifier->classifyCalls);
    }
}
