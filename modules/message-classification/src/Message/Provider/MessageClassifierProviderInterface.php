<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Message\Provider;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;

interface MessageClassifierProviderInterface
{
    /**
     * Picks one of `$tags` (by name) for the given text, with the probability of every tag. With
     * `$translate`, the classifier reads an English translation of the text (more accurate).
     *
     * @param MonitorTag[] $tags
     */
    public function classify(string $text, array $tags, bool $translate = true): MessageClassification;
}
