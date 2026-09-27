<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Db;

/**
 * @template TCreateData
 * @template TUpdateData
 * @template TEntity
 * @template TPageParams
 * @extends CreateRepositoryInterface<TCreateData, TEntity>
 * @extends UpdateRepositoryInterface<TUpdateData, TEntity>
 * @extends FindByIdRepositoryInterface<TEntity>
 * @extends FindPageRepositoryInterface<TPageParams, TEntity>
 */
interface CrudRepositoryInterface extends
    CreateRepositoryInterface,
    UpdateRepositoryInterface,
    DeleteRepositoryInterface,
    FindByIdRepositoryInterface,
    FindPageRepositoryInterface
{
}
