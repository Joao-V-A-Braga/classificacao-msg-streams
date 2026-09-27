<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Message\Provider;

use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessagePageParams;
use ClassificacaoMsgStreams\MessageClassification\Tests\Mock\FakeMessageRepository;
use PHPUnit\Framework\TestCase;

final class MessagePageParamsTest extends TestCase
{
    private const MONITOR_ID = '3d6f1b2a-9c8e-4f7d-a1b2-c3d4e5f6a7b8';

    public function testCleansTheLabels(): void
    {
        $params = new MessagePageParams(self::MONITOR_ID, 1, 10, [' elogio ', '', 'elogio', 'pergunta']);

        self::assertSame(['elogio', 'pergunta'], $params->labels);
    }

    public function testFiltersByAnyOfSeveralLabels(): void
    {
        $repository = new FakeMessageRepository();
        foreach (['pergunta', 'elogio', 'spam', null] as $label) {
            $repository->create(new Message([
                'monitorId' => self::MONITOR_ID,
                'authorName' => 'a',
                'text' => 'oi',
                'receivedAt' => new \DateTimeImmutable(),
                'label' => $label,
            ]));
        }

        $page = $repository->findPage(new MessagePageParams(self::MONITOR_ID, 1, 10, ['pergunta', 'elogio']));
        $all = $repository->findPage(new MessagePageParams(self::MONITOR_ID, 1, 10));

        self::assertSame(2, $page->total);
        self::assertEqualsCanonicalizing(['pergunta', 'elogio'], array_map(static fn (Message $m) => $m->getLabel(), $page->items));
        self::assertSame(4, $all->total);
    }
}
