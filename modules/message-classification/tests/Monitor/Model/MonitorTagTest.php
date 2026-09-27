<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Monitor\Model;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;

final class MonitorTagTest extends TestCase
{
    public function testNormalizesWhitespaceAndEmptyDescription(): void
    {
        $tag = new MonitorTag("  dúvida   técnica ", '   ');

        self::assertSame('dúvida técnica', $tag->name);
        self::assertNull($tag->description);
        self::assertTrue($tag->isNamed('DÚVIDA TÉCNICA'));
    }

    public function testRoundTripsThroughArrays(): void
    {
        $tag = MonitorTag::fromArray(['name' => 'elogio', 'description' => 'praise']);

        self::assertSame(['name' => 'elogio', 'description' => 'praise'], $tag->toArray());
    }

    public function testRequiresAName(): void
    {
        $this->expectException(ValidationException::class);

        (new MonitorTag(''))->validate();
    }

    public function testLimitsNameAndDescriptionLengths(): void
    {
        try {
            (new MonitorTag(str_repeat('a', 41), str_repeat('b', 121)))->validate();
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $codes = array_map(static fn ($error) => $error->getMessage(), $exception->getErrors());
            self::assertSame(['monitor.tag.name.max.length', 'monitor.tag.description.max.length'], $codes);
        }
    }
}
