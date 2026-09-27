<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Model;

use Symfony\Component\Uid\Uuid;
use ClassificacaoMsgStreams\Shared\Validation\Rules\DateRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\RequiredRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\UuidRule;
use ClassificacaoMsgStreams\Shared\Validation\Validator;

/**
 * Base entity for the domain model. Mirrors Entity<TState> from the Node
 * catalog: state is lazily invalid until validate() is explicitly called
 * by the use case; only id/timestamps are checked eagerly here.
 *
 * @template TState of array<string, mixed>
 */
abstract class AbstractEntity
{
    /** @var array<string, mixed> */
    protected readonly array $props;

    /**
     * @param array<string, mixed> $state
     */
    public function __construct(array $state)
    {
        $createdAt = $state['createdAt'] ?? new \DateTimeImmutable();
        $updatedAt = $state['updatedAt'] ?? $createdAt;

        $this->props = array_merge($state, [
            'id' => $state['id'] ?? Uuid::v4()->toRfc4122(),
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
            'deletedAt' => $state['deletedAt'] ?? null,
        ]);

        $this->validateId();
        $this->validateTimestamps();
    }

    public function getId(): string
    {
        return $this->props['id'];
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->props['createdAt'];
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->props['updatedAt'];
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->props['deletedAt'];
    }

    public function equals(?self $entity): bool
    {
        return $entity !== null && $this->getId() === $entity->getId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function clone(array $data): static
    {
        $next = array_merge($this->props, $data, [
            'updatedAt' => $data['updatedAt'] ?? new \DateTimeImmutable(),
        ]);

        return new static($next);
    }

    /**
     * Explicit, lazy domain validation. Never called from the constructor.
     * The entity may exist temporarily invalid; the caller (use case) must
     * invoke validate() before persisting.
     */
    abstract public function validate(): void;

    private function validateId(): void
    {
        Validator::validate([
            [
                'code' => 'id',
                'value' => $this->getId(),
                'rules' => [new RequiredRule(), new UuidRule()],
            ],
        ]);
    }

    private function validateTimestamps(): void
    {
        Validator::validate([
            [
                'code' => 'createdAt',
                'value' => $this->getCreatedAt(),
                'rules' => [new RequiredRule(), new DateRule()],
            ],
            [
                'code' => 'updatedAt',
                'value' => $this->getUpdatedAt(),
                'rules' => [new RequiredRule(), new DateRule()],
            ],
            [
                'code' => 'deletedAt',
                'value' => $this->getDeletedAt(),
                'rules' => [new DateRule()],
            ],
        ]);
    }
}
