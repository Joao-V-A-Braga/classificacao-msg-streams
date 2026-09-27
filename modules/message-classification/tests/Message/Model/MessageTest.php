<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Message\Model;

use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    private const MONITOR_ID = '3d6f1b2a-9c8e-4f7d-a1b2-c3d4e5f6a7b8';

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validProps(array $overrides = []): array
    {
        return array_merge([
            'monitorId' => self::MONITOR_ID,
            'externalId' => 'ChwKGkNJ',
            'authorName' => 'viewer123',
            'text' => 'Ótimo conteúdo!',
            'receivedAt' => new \DateTimeImmutable('2026-09-25T10:00:00+00:00'),
            'label' => null,
            'classifiedAt' => null,
        ], $overrides);
    }

    /**
     * @return string[]
     */
    private function errorCodes(callable $callback): array
    {
        try {
            $callback();

            return [];
        } catch (ValidationException $exception) {
            return array_map(static fn ($error) => $error->getMessage(), $exception->getErrors());
        }
    }

    public function testCreatesAValidEntityAndExposesAllGetters(): void
    {
        $props = $this->validProps();
        $message = new Message($props);

        self::assertSame(self::MONITOR_ID, $message->getMonitorId());
        self::assertSame('ChwKGkNJ', $message->getExternalId());
        self::assertSame($props['authorName'], $message->getAuthorName());
        self::assertSame($props['text'], $message->getText());
        self::assertSame($props['receivedAt'], $message->getReceivedAt());
        self::assertNull($message->getLabel());
        self::assertNull($message->getLabelProbabilities());
        self::assertNull($message->getClassifiedAt());
        self::assertSame([], $this->errorCodes(static fn () => $message->validate()));
    }

    public function testExposesTheClassificationWhenAlreadyClassified(): void
    {
        $classifiedAt = new \DateTimeImmutable('2026-09-25T10:05:00+00:00');
        $probabilities = ['pergunta' => 0.1, 'elogio' => 0.9];
        $message = new Message($this->validProps([
            'label' => 'elogio',
            'labelProbabilities' => $probabilities,
            'classifiedAt' => $classifiedAt,
        ]));

        self::assertSame('elogio', $message->getLabel());
        self::assertSame($probabilities, $message->getLabelProbabilities());
        self::assertSame($classifiedAt, $message->getClassifiedAt());
    }

    public function testExternalIdIsOptional(): void
    {
        $message = new Message($this->validProps(['externalId' => null]));

        self::assertNull($message->getExternalId());
        self::assertSame([], $this->errorCodes(static fn () => $message->validate()));
    }

    public function testDoesNotValidateOnConstruction(): void
    {
        $message = new Message($this->validProps(['text' => '']));

        self::assertSame('', $message->getText());
    }

    public function testRequiresAValidMonitorId(): void
    {
        self::assertContains('message.monitorId.invalid.uuid', $this->errorCodes(
            fn () => (new Message($this->validProps(['monitorId' => 'nope'])))->validate(),
        ));
    }

    public function testRequiresAuthorAndText(): void
    {
        $codes = $this->errorCodes(
            fn () => (new Message($this->validProps(['authorName' => '', 'text' => ' '])))->validate(),
        );

        self::assertContains('message.authorName.required', $codes);
        self::assertContains('message.text.required', $codes);
    }

    public function testLimitsTheTextLength(): void
    {
        self::assertContains('message.text.max.length', $this->errorCodes(
            fn () => (new Message($this->validProps(['text' => str_repeat('a', 5001)])))->validate(),
        ));
    }

    public function testLimitsTheLabelToTheTagNameLength(): void
    {
        self::assertContains('message.label.max.length', $this->errorCodes(
            fn () => (new Message($this->validProps(['label' => str_repeat('a', 41)])))->validate(),
        ));
    }

    public function testRequiresReceivedAt(): void
    {
        self::assertContains('message.receivedAt.required', $this->errorCodes(
            fn () => (new Message($this->validProps(['receivedAt' => null])))->validate(),
        ));
    }

    public function testCloneKeepsTheIdentity(): void
    {
        $message = new Message($this->validProps());
        $classified = $message->clone(['label' => 'elogio']);

        self::assertTrue($message->equals($classified));
        self::assertSame('elogio', $classified->getLabel());
        self::assertNull($message->getLabel());
    }
}
