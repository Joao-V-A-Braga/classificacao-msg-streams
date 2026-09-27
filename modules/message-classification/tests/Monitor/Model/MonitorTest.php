<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Monitor\Model;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\MonitorFixture;
use ClassificacaoMsgStreams\Shared\Error\ForbiddenError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;

final class MonitorTest extends TestCase
{
    /**
     * @return string[]
     */
    private function errorCodes(Monitor $monitor): array
    {
        try {
            $monitor->validate();

            return [];
        } catch (ValidationException $exception) {
            return array_map(static fn ($error) => $error->getMessage(), $exception->getErrors());
        }
    }

    public function testCreatesAValidPausedMonitor(): void
    {
        $monitor = MonitorFixture::monitor();

        self::assertSame(MonitorFixture::USER_ID, $monitor->getUserId());
        self::assertSame('Live de lançamento', $monitor->getName());
        self::assertSame(Monitor::SOURCE_YOUTUBE_LIVE, $monitor->getSourceType());
        self::assertSame(Monitor::STATUS_PAUSED, $monitor->getStatus());
        self::assertFalse($monitor->isRunning());
        self::assertNull($monitor->getStartedAt());
        self::assertNull($monitor->getRunnerState());
        self::assertSame(['pergunta', 'elogio', 'outro'], $monitor->getTagNames());
        self::assertSame([], $this->errorCodes($monitor));
    }

    public function testFindsTagsIgnoringCaseAndExtraSpaces(): void
    {
        $monitor = MonitorFixture::monitor();

        self::assertSame('elogio', $monitor->findTag('  ELOGIO ')?->name);
        self::assertNull($monitor->findTag('spam'));
    }

    public function testRequiresNameUrlAndAKnownSourceType(): void
    {
        $codes = $this->errorCodes(MonitorFixture::monitor(['name' => '', 'url' => '', 'sourceType' => 'tiktok']));

        self::assertContains('monitor.name.required', $codes);
        self::assertContains('monitor.url.required', $codes);
        self::assertContains('monitor.sourceType.invalid.choice', $codes);
    }

    public function testRejectsSomethingThatIsNotAUrl(): void
    {
        self::assertContains('monitor.url.invalid.url', $this->errorCodes(MonitorFixture::monitor(['url' => 'youtube.com/live'])));
    }

    public function testRejectsAUrlFromAnotherPlatform(): void
    {
        self::assertSame(['monitor.url.source.mismatch'], $this->errorCodes(
            MonitorFixture::monitor(['url' => 'https://www.instagram.com/p/xyz/']),
        ));
        self::assertSame([], $this->errorCodes(MonitorFixture::monitor([
            'sourceType' => Monitor::SOURCE_INSTAGRAM_POST,
            'url' => 'https://www.instagram.com/p/xyz/',
        ])));
        self::assertSame([], $this->errorCodes(MonitorFixture::monitor(['url' => 'https://youtu.be/abc'])));
    }

    public function testRejectsDuplicatedTagNames(): void
    {
        self::assertSame(['monitor.tag.name.duplicated'], $this->errorCodes(MonitorFixture::monitor([
            'tags' => [new MonitorTag('Elogio'), new MonitorTag('elogio ')],
        ])));
    }

    public function testRejectsTooManyTags(): void
    {
        $tags = array_map(static fn (int $i) => new MonitorTag("tag {$i}"), range(1, Monitor::MAX_TAGS + 1));

        self::assertContains('monitor.tags.max.length', $this->errorCodes(MonitorFixture::monitor(['tags' => $tags])));
    }

    public function testRejectsAnUnknownRunnerState(): void
    {
        self::assertContains('monitor.runnerState.invalid.choice', $this->errorCodes(
            MonitorFixture::running(['runnerState' => 'dancing']),
        ));
    }

    public function testOwnershipCheck(): void
    {
        $monitor = MonitorFixture::monitor();
        $monitor->assertOwnedBy(MonitorFixture::USER_ID);

        $this->expectException(ForbiddenError::class);
        $monitor->assertOwnedBy(MonitorFixture::OTHER_USER_ID);
    }
}
