<?php

declare(strict_types=1);

namespace App\Modules\MessageClassification\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'monitors')]
#[ORM\Index(name: 'idx_monitors_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_monitors_status', columns: ['status'])]
class MonitorRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(type: 'string', length: 36)]
    public string $userId;

    #[ORM\Column(type: 'string', length: 120)]
    public string $name;

    #[ORM\Column(type: 'string', length: 32)]
    public string $sourceType;

    #[ORM\Column(type: 'string', length: 2048)]
    public string $url;

    #[ORM\Column(type: 'string', length: 16)]
    public string $status;

    /** @var array<int, array{name: string, description: string|null}> */
    #[ORM\Column(type: 'json')]
    public array $tags = [];

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    public bool $includeExistingComments = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    public bool $translateForLaya = true;

    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    public ?string $runnerState = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    public ?string $runnerDetail = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $deletedAt = null;
}
