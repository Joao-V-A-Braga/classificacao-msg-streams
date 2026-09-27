<?php

declare(strict_types=1);

namespace App\Modules\MessageClassification\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'messages')]
#[ORM\Index(name: 'idx_messages_monitor_received', columns: ['monitor_id', 'received_at'])]
#[ORM\UniqueConstraint(name: 'uniq_messages_monitor_external', columns: ['monitor_id', 'external_id'])]
class MessageRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(type: 'string', length: 36)]
    public string $monitorId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $externalId = null;

    #[ORM\Column(type: 'string', length: 255)]
    public string $authorName;

    #[ORM\Column(type: 'text')]
    public string $text;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $receivedAt;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    public ?string $label = null;

    /** @var array<string, float>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $labelProbabilities = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $translatedText = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $classifiedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $deletedAt = null;
}
