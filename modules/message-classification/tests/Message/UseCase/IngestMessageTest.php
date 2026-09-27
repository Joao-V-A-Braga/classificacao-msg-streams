<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Message\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Message\UseCase\IngestMessage;
use ClassificacaoMsgStreams\MessageClassification\Message\UseCase\IngestMessageIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMessageRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMonitorRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\MonitorFixture;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;

final class IngestMessageTest extends TestCase
{
    private FakeMessageRepository $messageRepository;

    private FakeMonitorRepository $monitorRepository;

    private IngestMessage $useCase;

    protected function setUp(): void
    {
        $this->messageRepository = new FakeMessageRepository();
        $this->monitorRepository = new FakeMonitorRepository();
        $this->useCase = new IngestMessage($this->messageRepository, $this->monitorRepository);
    }

    private function input(Monitor $monitor, array $overrides = []): IngestMessageIn
    {
        return new IngestMessageIn(...array_merge([
            'monitorId' => $monitor->getId(),
            'authorName' => 'viewer123',
            'text' => 'Ótimo conteúdo!',
            'receivedAt' => new \DateTimeImmutable('2026-09-26T10:01:00+00:00'),
            'externalId' => 'yt-1',
        ], $overrides));
    }

    public function testIngestsAMessageIntoARunningMonitor(): void
    {
        $monitor = $this->monitorRepository->create(MonitorFixture::running());

        $result = $this->useCase->execute($this->input($monitor));

        self::assertTrue($result->created);
        self::assertSame($monitor->getId(), $result->message->getMonitorId());
        self::assertSame('yt-1', $result->message->getExternalId());
        self::assertNull($result->message->getLabel());
        self::assertNotNull($this->messageRepository->findById($result->message->getId()));
        self::assertTrue($result->monitor->equals($monitor));
    }

    public function testDoesNotIngestTheSameExternalIdTwice(): void
    {
        $monitor = $this->monitorRepository->create(MonitorFixture::running());

        $first = $this->useCase->execute($this->input($monitor));
        $second = $this->useCase->execute($this->input($monitor, ['text' => 'outro texto']));

        self::assertFalse($second->created);
        self::assertTrue($first->message->equals($second->message));
        self::assertSame(1, $this->messageRepository->findPage(
            new \ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessagePageParams($monitor->getId(), 1, 10),
        )->total);
    }

    public function testRejectsMessagesForAPausedMonitor(): void
    {
        $monitor = $this->monitorRepository->create(MonitorFixture::monitor());

        $this->expectException(ConflictError::class);
        $this->expectExceptionMessage('monitor.not_running');

        $this->useCase->execute($this->input($monitor));
    }

    public function testRejectsMessagesForAnUnknownMonitor(): void
    {
        $this->expectException(NotFoundError::class);
        $this->expectExceptionMessage('monitor.not_found');

        $this->useCase->execute($this->input(MonitorFixture::running()));
    }

    public function testPropagatesValidationFailureWithoutPersisting(): void
    {
        $monitor = $this->monitorRepository->create(MonitorFixture::running());

        try {
            $this->useCase->execute($this->input($monitor, ['text' => '']));
            self::fail('Expected ValidationException');
        } catch (ValidationException) {
        }

        self::assertSame([], $this->messageRepository->countByLabel($monitor->getId()));
    }
}
