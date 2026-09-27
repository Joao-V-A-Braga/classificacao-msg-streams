<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Message\Provider;

final class MessageClassification
{
    /**
     * @param array<string, float> $probabilities probability of each tag name
     * @param string|null $translatedText the English version the classifier read, when it translated
     */
    public function __construct(
        public readonly string $label,
        public readonly array $probabilities,
        public readonly ?string $translatedText = null,
    ) {
    }
}
