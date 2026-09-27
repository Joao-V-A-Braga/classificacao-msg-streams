<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Db;

/**
 * @template TPageParams
 * @template TItem
 */
interface FindPageRepositoryInterface
{
    /**
     * @param TPageParams $params
     * @return PageResult<TItem>
     */
    public function findPage(mixed $params): PageResult;
}
