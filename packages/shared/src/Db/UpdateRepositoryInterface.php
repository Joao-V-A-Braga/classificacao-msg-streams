<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Db;

/**
 * @template TUpdateData
 * @template TEntity
 */
interface UpdateRepositoryInterface
{
    /**
     * @param TUpdateData $data
     * @return TEntity
     */
    public function update(mixed $data): mixed;
}
