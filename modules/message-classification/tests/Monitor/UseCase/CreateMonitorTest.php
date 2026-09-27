<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Monitor\UseCase;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\CreateMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\CreateMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMonitorRepository;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\MonitorFixture;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;

final class CreateMonitorTest extends TestCase
{
    public function testCreatesAPausedMonitorWithItsTags(): void
    {
        $repository = new FakeMonitorRepository();

        $result = (new CreateMonitor($repository))->execute(new CreateMonitorIn(
            userId: MonitorFixture::USER_ID,
            name: ' Live de sexta ',
            sourceType: Monitor::SOURCE_YOUTUBE_LIVE,
            url: 'https://www.youtube.com/watch?v=abc',
            tags: [['name' => 'pergunta', 'description' => 'a question'], ['name' => 'elogio']],
        ));

        self::assertSame('Live de sexta', $result->monitor->getName());
        self::assertSame(Monitor::STATUS_PAUSED, $result->monitor->getStatus());
        self::assertSame(['pergunta', 'elogio'], $result->monitor->getTagNames());
        self::assertSame('a question', $result->monitor->findTag('pergunta')?->description);
        self::assertNotNull($repository->findById($result->monitor->getId()));
    }

    public function testRejectsAnInvalidMonitorWithoutPersisting(): void
    {
        $repository = new FakeMonitorRepository();

        try {
            (new CreateMonitor($repository))->execute(new CreateMonitorIn(
                userId: MonitorFixture::USER_ID,
                name: '',
                sourceType: Monitor::SOURCE_YOUTUBE_LIVE,
                url: 'https://www.youtube.com/watch?v=abc',
            ));
            self::fail('Expected ValidationException');
        } catch (ValidationException) {
        }

        self::assertSame(0, $repository->findPage(
            new \ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorPageParams(MonitorFixture::USER_ID, 1, 10),
        )->total);
    }
}
