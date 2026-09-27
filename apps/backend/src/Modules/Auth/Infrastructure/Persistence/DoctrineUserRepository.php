<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Modules\Auth\Infrastructure\Persistence\Doctrine\UserRecord;
use ClassificacaoMsgStreams\Auth\User\Model\User;
use ClassificacaoMsgStreams\Auth\User\Provider\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function create(mixed $data): mixed
    {
        $record = $this->toRecord($data);
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        return $this->toDomain($record);
    }

    public function findById(string $id): mixed
    {
        $record = $this->entityManager->find(UserRecord::class, $id);

        return $record !== null ? $this->toDomain($record) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $record = $this->entityManager
            ->getRepository(UserRecord::class)
            ->findOneBy(['email' => $email]);

        return $record !== null ? $this->toDomain($record) : null;
    }

    private function toRecord(User $user, ?UserRecord $record = null): UserRecord
    {
        $record ??= new UserRecord();
        $record->id = $user->getId();
        $record->name = $user->getName();
        $record->email = $user->getEmail();
        $record->password = $user->getPassword();
        $record->createdAt = $user->getCreatedAt();
        $record->updatedAt = $user->getUpdatedAt();
        $record->deletedAt = $user->getDeletedAt();

        return $record;
    }

    private function toDomain(UserRecord $record): User
    {
        return new User([
            'id' => $record->id,
            'name' => $record->name,
            'email' => $record->email,
            'password' => $record->password,
            'createdAt' => $record->createdAt,
            'updatedAt' => $record->updatedAt,
            'deletedAt' => $record->deletedAt,
        ]);
    }
}
