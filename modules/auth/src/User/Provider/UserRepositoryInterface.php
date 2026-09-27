<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\User\Provider;

use ClassificacaoMsgStreams\Auth\User\Model\User;
use ClassificacaoMsgStreams\Shared\Db\CreateRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Db\FindByIdRepositoryInterface;

/**
 * @extends CreateRepositoryInterface<User, User>
 * @extends FindByIdRepositoryInterface<User>
 */
interface UserRepositoryInterface extends CreateRepositoryInterface, FindByIdRepositoryInterface
{
    public function findByEmail(string $email): ?User;
}
