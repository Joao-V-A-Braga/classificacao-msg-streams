<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Message\Provider;

/**
 * Messages of one monitor, newest first; `labels` narrows them to any of these tags (empty = all).
 */
final class MessagePageParams
{
    /** @var string[] */
    public readonly array $labels;

    /**
     * @param string[] $labels
     */
    public function __construct(
        public readonly string $monitorId,
        public readonly int $page,
        public readonly int $perPage,
        array $labels = [],
    ) {
        $this->labels = array_values(array_unique(array_filter(
            array_map(static fn (mixed $label): string => trim((string) $label), $labels),
            static fn (string $label): bool => $label !== '',
        )));
    }
}
