<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Mock;

use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageClassification;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageClassifierProviderInterface;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;

final class FakeMessageClassifierProvider implements MessageClassifierProviderInterface
{
    public int $classifyCalls = 0;

    public ?string $lastText = null;

    /** @var MonitorTag[] */
    public array $lastTags = [];

    public ?bool $lastTranslate = null;

    /**
     * @param array<string, float> $probabilities
     */
    public function __construct(
        private readonly string $label = 'outro',
        private readonly array $probabilities = ['outro' => 1.0],
        private readonly ?string $translatedText = null,
    ) {
    }

    public function classify(string $text, array $tags, bool $translate = true): MessageClassification
    {
        $this->classifyCalls++;
        $this->lastText = $text;
        $this->lastTags = $tags;
        $this->lastTranslate = $translate;

        return new MessageClassification($this->label, $this->probabilities, $this->translatedText);
    }
}
