<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Db;

/**
 * @template TCreateData
 * @template TEntity
 */
interface CreateRepositoryInterface
{
    /**
     * @param TCreateData $data
     * @return TEntity
     */
    public function create(mixed $data): mixed;
}
