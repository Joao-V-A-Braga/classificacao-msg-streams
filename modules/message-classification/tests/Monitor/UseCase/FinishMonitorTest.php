<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\FinishMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\FinishMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\StartMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\StartMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMonitorRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\MonitorFixture;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use PHPUnit\Framework\TestCase;

final class FinishMonitorTest extends TestCase
{
    public function testPausesTheMonitorAndKeepsTheReason(): void
    {
        $repository = new FakeMonitorRepository();
        $monitor = $repository->create(MonitorFixture::running(['runnerState' => 'error']));

        $result = (new FinishMonitor($repository))->execute(new FinishMonitorIn($monitor->getId(), '  A live terminou.  '));

        self::assertSame(Monitor::STATUS_PAUSED, $result->monitor->getStatus());
        self::assertNull($result->monitor->getRunnerState());
        self::assertSame('A live terminou.', $result->monitor->getRunnerDetail());
        self::assertFalse($repository->findById($monitor->getId())->isRunning());
    }

    public function testTheReasonIsClearedWhenStartedAgain(): void
    {
        $repository = new FakeMonitorRepository();
        $monitor = $repository->create(MonitorFixture::running());
        (new FinishMonitor($repository))->execute(new FinishMonitorIn($monitor->getId(), 'A live terminou.'));

        $started = (new StartMonitor($repository))->execute(new StartMonitorIn($monitor->getId(), MonitorFixture::USER_ID));

        self::assertNull($started->monitor->getRunnerDetail());
    }

    public function testAPausedMonitorTellsTheRunnerToStop(): void
    {
        $repository = new FakeMonitorRepository();
        $monitor = $repository->create(MonitorFixture::monitor());

        $this->expectException(ConflictError::class);

        (new FinishMonitor($repository))->execute(new FinishMonitorIn($monitor->getId(), 'x'));
    }
}
