<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Db;

/**
 * @template TItem
 */
final class PageResult
{
    /**
     * @param TItem[] $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
    ) {
    }
}
