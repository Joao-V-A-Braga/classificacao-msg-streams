<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\Tests\Mock;

use ClassificacaoMsgStreams\Auth\User\Model\User;
use ClassificacaoMsgStreams\Auth\User\Provider\UserRepositoryInterface;

final class FakeUserRepository implements UserRepositoryInterface
{
    /**
     * @var array<string, User>
     */
    private array $users = [];

    public function create(mixed $data): mixed
    {
        $this->users[$data->getId()] = $data;

        return $data;
    }

    public function findById(string $id): mixed
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getEmail() === $email) {
                return $user;
            }
        }

        return null;
    }
}
