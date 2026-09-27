<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\ReportMonitorRunnerState;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\ReportMonitorRunnerStateIn;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMonitorRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\MonitorFixture;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;

final class ReportMonitorRunnerStateTest extends TestCase
{
    public function testStoresTheStateReportedByTheRunner(): void
    {
        $repository = new FakeMonitorRepository();
        $monitor = $repository->create(MonitorFixture::running(['runnerState' => 'starting']));

        $result = (new ReportMonitorRunnerState($repository))->execute(
            new ReportMonitorRunnerStateIn($monitor->getId(), 'error', '  Chrome não respondeu  '),
        );

        self::assertSame('error', $result->monitor->getRunnerState());
        self::assertSame('Chrome não respondeu', $result->monitor->getRunnerDetail());
        self::assertSame('error', $repository->findById($monitor->getId())->getRunnerState());
    }

    public function testTruncatesLongDetails(): void
    {
        $repository = new FakeMonitorRepository();
        $monitor = $repository->create(MonitorFixture::running());

        $result = (new ReportMonitorRunnerState($repository))->execute(
            new ReportMonitorRunnerStateIn($monitor->getId(), 'error', str_repeat('x', 900)),
        );

        self::assertSame(500, mb_strlen((string) $result->monitor->getRunnerDetail()));
    }

    public function testTellsTheRunnerToStopWhenThePipelineIsPaused(): void
    {
        $repository = new FakeMonitorRepository();
        $monitor = $repository->create(MonitorFixture::monitor());

        $this->expectException(ConflictError::class);

        (new ReportMonitorRunnerState($repository))->execute(new ReportMonitorRunnerStateIn($monitor->getId(), 'watching'));
    }

    public function testRejectsUnknownStates(): void
    {
        $repository = new FakeMonitorRepository();
        $monitor = $repository->create(MonitorFixture::running());

        $this->expectException(ValidationException::class);

        (new ReportMonitorRunnerState($repository))->execute(new ReportMonitorRunnerStateIn($monitor->getId(), 'sleeping'));
    }
}
