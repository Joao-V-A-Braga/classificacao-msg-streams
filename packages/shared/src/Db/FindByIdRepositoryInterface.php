<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Db;

/**
 * @template TEntity
 */
interface FindByIdRepositoryInterface
{
    /**
     * @return TEntity|null
     */
    public function findById(string $id): mixed;
}
