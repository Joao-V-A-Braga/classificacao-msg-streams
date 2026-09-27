<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\DeleteMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\DeleteMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMessageRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMonitorRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\MonitorFixture;
use ClassificacaoMsgStreams\Shared\Error\ForbiddenError;
use PHPUnit\Framework\TestCase;

final class DeleteMonitorTest extends TestCase
{
    public function testDeletesTheMonitorAndOnlyItsMessages(): void
    {
        $monitors = new FakeMonitorRepository();
        $messages = new FakeMessageRepository();
        $monitor = $monitors->create(MonitorFixture::running());
        $other = $monitors->create(MonitorFixture::monitor());
        foreach ([$monitor, $other] as $owner) {
            $messages->create(new Message([
                'monitorId' => $owner->getId(),
                'authorName' => 'a',
                'text' => 'oi',
                'receivedAt' => new \DateTimeImmutable(),
            ]));
        }

        (new DeleteMonitor($monitors, $messages))->execute(new DeleteMonitorIn($monitor->getId(), MonitorFixture::USER_ID));

        self::assertNull($monitors->findById($monitor->getId()));
        self::assertSame([], $messages->countByLabel($monitor->getId()));
        self::assertSame(['' => 1], $messages->countByLabel($other->getId()));
    }

    public function testARunningMonitorIsStoppedBeforeBeingDeleted(): void
    {
        $monitors = new FakeMonitorRepository();
        $monitor = $monitors->create(MonitorFixture::running());

        $result = (new DeleteMonitor($monitors, new FakeMessageRepository()))
            ->execute(new DeleteMonitorIn($monitor->getId(), MonitorFixture::USER_ID));

        self::assertSame(1, $monitors->updateCalls);
        self::assertFalse($result->monitor->isRunning());
        self::assertNull($monitors->findById($monitor->getId()));
    }

    public function testAPausedMonitorIsDeletedStraightAway(): void
    {
        $monitors = new FakeMonitorRepository();
        $monitor = $monitors->create(MonitorFixture::monitor());

        (new DeleteMonitor($monitors, new FakeMessageRepository()))
            ->execute(new DeleteMonitorIn($monitor->getId(), MonitorFixture::USER_ID));

        self::assertSame(0, $monitors->updateCalls);
    }

    public function testCannotDeleteSomeoneElsesMonitor(): void
    {
        $monitors = new FakeMonitorRepository();
        $monitor = $monitors->create(MonitorFixture::monitor());

        try {
            (new DeleteMonitor($monitors, new FakeMessageRepository()))
                ->execute(new DeleteMonitorIn($monitor->getId(), MonitorFixture::OTHER_USER_ID));
            self::fail('Expected ForbiddenError');
        } catch (ForbiddenError) {
        }

        self::assertNotNull($monitors->findById($monitor->getId()));
    }
}
