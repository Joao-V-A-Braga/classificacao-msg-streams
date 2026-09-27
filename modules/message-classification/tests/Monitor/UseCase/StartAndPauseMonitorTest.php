<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\PauseMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\PauseMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\StartMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\StartMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMonitorRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\MonitorFixture;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\ForbiddenError;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use PHPUnit\Framework\TestCase;

final class StartAndPauseMonitorTest extends TestCase
{
    private FakeMonitorRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new FakeMonitorRepository();
    }

    public function testStartingRunsTheMonitorFromNow(): void
    {
        $monitor = $this->repository->create(MonitorFixture::monitor());
        $before = new \DateTimeImmutable();

        $result = (new StartMonitor($this->repository))->execute(new StartMonitorIn($monitor->getId(), MonitorFixture::USER_ID));

        self::assertTrue($result->monitor->isRunning());
        self::assertGreaterThanOrEqual($before, $result->monitor->getStartedAt());
        self::assertSame('starting', $result->monitor->getRunnerState());
        self::assertTrue($this->repository->findById($monitor->getId())->isRunning());
    }

    public function testEveryStartMovesStartedAtForward(): void
    {
        $old = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $monitor = $this->repository->create(MonitorFixture::monitor(['startedAt' => $old]));

        $result = (new StartMonitor($this->repository))->execute(new StartMonitorIn($monitor->getId(), MonitorFixture::USER_ID));

        self::assertGreaterThan($old, $result->monitor->getStartedAt());
    }

    public function testCannotStartWithLessThanTwoTags(): void
    {
        $monitor = $this->repository->create(MonitorFixture::monitor(['tags' => [new MonitorTag('pergunta')]]));

        $this->expectException(ConflictError::class);
        $this->expectExceptionMessage('monitor.tags.not_enough');

        (new StartMonitor($this->repository))->execute(new StartMonitorIn($monitor->getId(), MonitorFixture::USER_ID));
    }

    public function testCannotStartTwice(): void
    {
        $monitor = $this->repository->create(MonitorFixture::running());

        $this->expectException(ConflictError::class);
        $this->expectExceptionMessage('monitor.already_running');

        (new StartMonitor($this->repository))->execute(new StartMonitorIn($monitor->getId(), MonitorFixture::USER_ID));
    }

    public function testCannotStartSomeoneElsesMonitor(): void
    {
        $monitor = $this->repository->create(MonitorFixture::monitor());

        $this->expectException(ForbiddenError::class);

        (new StartMonitor($this->repository))->execute(new StartMonitorIn($monitor->getId(), MonitorFixture::OTHER_USER_ID));
    }

    public function testCannotStartAnUnknownMonitor(): void
    {
        $this->expectException(NotFoundError::class);

        (new StartMonitor($this->repository))->execute(new StartMonitorIn(MonitorFixture::OTHER_USER_ID, MonitorFixture::USER_ID));
    }

    public function testPausingStopsTheMonitorAndClearsTheRunnerState(): void
    {
        $monitor = $this->repository->create(MonitorFixture::running());

        $result = (new PauseMonitor($this->repository))->execute(new PauseMonitorIn($monitor->getId(), MonitorFixture::USER_ID));

        self::assertSame(Monitor::STATUS_PAUSED, $result->monitor->getStatus());
        self::assertNull($result->monitor->getRunnerState());
        self::assertNotNull($result->monitor->getStartedAt());
    }

    public function testCannotPauseAPausedMonitor(): void
    {
        $monitor = $this->repository->create(MonitorFixture::monitor());

        $this->expectException(ConflictError::class);
        $this->expectExceptionMessage('monitor.not_running');

        (new PauseMonitor($this->repository))->execute(new PauseMonitorIn($monitor->getId(), MonitorFixture::USER_ID));
    }
}
