<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\ConfigureMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\ConfigureMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\CreateMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\CreateMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMonitorRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\MonitorFixture;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\ForbiddenError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;

final class ConfigureMonitorTest extends TestCase
{
    private FakeMonitorRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new FakeMonitorRepository();
    }

    private function instagramPost(array $overrides = []): Monitor
    {
        return $this->repository->create(MonitorFixture::monitor(array_merge([
            'sourceType' => Monitor::SOURCE_INSTAGRAM_POST,
            'url' => 'https://www.instagram.com/p/abc/',
        ], $overrides)));
    }

    public function testExistingCommentsAreOffByDefault(): void
    {
        self::assertFalse($this->instagramPost()->includesExistingComments());
    }

    public function testAnInstagramPostCanBeCreatedImportingExistingComments(): void
    {
        $result = (new CreateMonitor($this->repository))->execute(new CreateMonitorIn(
            userId: MonitorFixture::USER_ID,
            name: 'Post',
            sourceType: Monitor::SOURCE_INSTAGRAM_POST,
            url: 'https://www.instagram.com/reel/abc/',
            tags: [['name' => 'a'], ['name' => 'b']],
            includeExistingComments: true,
        ));

        self::assertTrue($result->monitor->includesExistingComments());
    }

    public function testALiveCannotImportExistingComments(): void
    {
        $this->expectException(ValidationException::class);

        (new CreateMonitor($this->repository))->execute(new CreateMonitorIn(
            userId: MonitorFixture::USER_ID,
            name: 'Live',
            sourceType: Monitor::SOURCE_YOUTUBE_LIVE,
            url: 'https://www.youtube.com/watch?v=abc',
            includeExistingComments: true,
        ));
    }

    public function testTurnsTheSettingOnAndOffWhilePaused(): void
    {
        $monitor = $this->instagramPost();
        $useCase = new ConfigureMonitor($this->repository);

        $on = $useCase->execute(new ConfigureMonitorIn($monitor->getId(), MonitorFixture::USER_ID, true));
        self::assertTrue($on->monitor->includesExistingComments());
        self::assertTrue($this->repository->findById($monitor->getId())->includesExistingComments());

        $off = $useCase->execute(new ConfigureMonitorIn($monitor->getId(), MonitorFixture::USER_ID, false));
        self::assertFalse($off->monitor->includesExistingComments());
    }

    public function testTranslationIsOnByDefault(): void
    {
        self::assertTrue($this->instagramPost()->translatesForLaya());
        self::assertTrue(MonitorFixture::monitor()->translatesForLaya());
    }

    public function testTranslationCanBeTurnedOffWhileRunning(): void
    {
        $monitor = $this->repository->create(MonitorFixture::running());

        $result = (new ConfigureMonitor($this->repository))->execute(
            new ConfigureMonitorIn($monitor->getId(), MonitorFixture::USER_ID, translateForLaya: false),
        );

        self::assertFalse($result->monitor->translatesForLaya());
        self::assertTrue($result->monitor->isRunning());
        self::assertFalse($this->repository->findById($monitor->getId())->translatesForLaya());
    }

    public function testChangingOnlyTheTranslationLeavesTheOtherSettingAlone(): void
    {
        $monitor = $this->instagramPost(['includeExistingComments' => true]);

        $result = (new ConfigureMonitor($this->repository))->execute(
            new ConfigureMonitorIn($monitor->getId(), MonitorFixture::USER_ID, translateForLaya: false),
        );

        self::assertTrue($result->monitor->includesExistingComments());
    }

    public function testCannotChangeTheSettingWhileRunning(): void
    {
        $monitor = $this->instagramPost(['status' => Monitor::STATUS_RUNNING, 'startedAt' => new \DateTimeImmutable()]);

        $this->expectException(ConflictError::class);
        $this->expectExceptionMessage('monitor.must_be_paused');

        (new ConfigureMonitor($this->repository))->execute(new ConfigureMonitorIn($monitor->getId(), MonitorFixture::USER_ID, true));
    }

    public function testCannotConfigureSomeoneElsesMonitor(): void
    {
        $monitor = $this->instagramPost();

        $this->expectException(ForbiddenError::class);

        (new ConfigureMonitor($this->repository))->execute(new ConfigureMonitorIn($monitor->getId(), MonitorFixture::OTHER_USER_ID, true));
    }
}
