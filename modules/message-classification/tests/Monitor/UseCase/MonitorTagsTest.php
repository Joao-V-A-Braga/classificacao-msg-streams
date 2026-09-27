<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\AddMonitorTag;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\AddMonitorTagIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\RemoveMonitorTag;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\RemoveMonitorTagIn;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMonitorRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\MonitorFixture;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\ForbiddenError;
use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMessageRepository;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;

final class MonitorTagsTest extends TestCase
{
    private FakeMonitorRepository $repository;

    private FakeMessageRepository $messages;

    protected function setUp(): void
    {
        $this->repository = new FakeMonitorRepository();
        $this->messages = new FakeMessageRepository();
    }

    private function removeTag(): RemoveMonitorTag
    {
        return new RemoveMonitorTag($this->repository, $this->messages);
    }

    public function testAddsATagToAPausedMonitor(): void
    {
        $monitor = $this->repository->create(MonitorFixture::monitor());

        $result = (new AddMonitorTag($this->repository))->execute(
            new AddMonitorTagIn($monitor->getId(), MonitorFixture::USER_ID, 'spam', 'advertising or links'),
        );

        self::assertSame(['pergunta', 'elogio', 'outro', 'spam'], $result->monitor->getTagNames());
        self::assertSame('advertising or links', $this->repository->findById($monitor->getId())->findTag('spam')?->description);
    }

    public function testCannotAddATagWhileRunning(): void
    {
        $monitor = $this->repository->create(MonitorFixture::running());

        $this->expectException(ConflictError::class);
        $this->expectExceptionMessage('monitor.must_be_paused');

        (new AddMonitorTag($this->repository))->execute(new AddMonitorTagIn($monitor->getId(), MonitorFixture::USER_ID, 'spam'));
    }

    public function testCannotAddADuplicatedTag(): void
    {
        $monitor = $this->repository->create(MonitorFixture::monitor());

        $this->expectException(ConflictError::class);
        $this->expectExceptionMessage('monitor.tag.already_exists');

        (new AddMonitorTag($this->repository))->execute(new AddMonitorTagIn($monitor->getId(), MonitorFixture::USER_ID, ' Elogio '));
    }

    public function testCannotAddAnEmptyTag(): void
    {
        $monitor = $this->repository->create(MonitorFixture::monitor());

        $this->expectException(ValidationException::class);

        (new AddMonitorTag($this->repository))->execute(new AddMonitorTagIn($monitor->getId(), MonitorFixture::USER_ID, '  '));
    }

    public function testCannotAddATagToSomeoneElsesMonitor(): void
    {
        $monitor = $this->repository->create(MonitorFixture::monitor());

        $this->expectException(ForbiddenError::class);

        (new AddMonitorTag($this->repository))->execute(new AddMonitorTagIn($monitor->getId(), MonitorFixture::OTHER_USER_ID, 'spam'));
    }

    public function testRemovesATagAndOnlyTheMessagesClassifiedWithIt(): void
    {
        $monitor = $this->repository->create(MonitorFixture::monitor());
        $other = $this->repository->create(MonitorFixture::monitor());
        foreach ([[$monitor, 'elogio'], [$monitor, 'elogio'], [$monitor, 'pergunta'], [$monitor, null], [$other, 'elogio']] as [$owner, $label]) {
            $this->messages->create(new Message([
                'monitorId' => $owner->getId(),
                'authorName' => 'a',
                'text' => 'oi',
                'receivedAt' => new \DateTimeImmutable(),
                'label' => $label,
            ]));
        }

        $result = $this->removeTag()->execute(new RemoveMonitorTagIn($monitor->getId(), MonitorFixture::USER_ID, 'ELOGIO'));

        self::assertSame(['pergunta', 'outro'], $result->monitor->getTagNames());
        self::assertSame(2, $result->deletedMessages);
        self::assertSame(['pergunta' => 1, '' => 1], $this->messages->countByLabel($monitor->getId()));
        self::assertSame(['elogio' => 1], $this->messages->countByLabel($other->getId()));
    }

    public function testCannotRemoveATagWhileRunning(): void
    {
        $monitor = $this->repository->create(MonitorFixture::running());

        $this->expectException(ConflictError::class);
        $this->expectExceptionMessage('monitor.must_be_paused');

        $this->removeTag()->execute(new RemoveMonitorTagIn($monitor->getId(), MonitorFixture::USER_ID, 'elogio'));
    }

    public function testCannotRemoveAnUnknownTag(): void
    {
        $monitor = $this->repository->create(MonitorFixture::monitor());

        $this->expectException(NotFoundError::class);
        $this->expectExceptionMessage('monitor.tag.not_found');

        $this->removeTag()->execute(new RemoveMonitorTagIn($monitor->getId(), MonitorFixture::USER_ID, 'spam'));
    }
}
