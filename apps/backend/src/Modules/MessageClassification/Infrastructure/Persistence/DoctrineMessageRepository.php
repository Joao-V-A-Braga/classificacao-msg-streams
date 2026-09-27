<?php

declare(strict_types=1);

namespace App\Modules\MessageClassification\Infrastructure\Persistence;

use App\Modules\MessageClassification\Infrastructure\Persistence\Doctrine\MessageRecord;
use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessagePageParams;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Db\PageResult;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineMessageRepository implements MessageRepositoryInterface
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

    public function update(mixed $data): mixed
    {
        $record = $this->entityManager->find(MessageRecord::class, $data->getId());

        if ($record === null) {
            throw new \RuntimeException("Message {$data->getId()} not found.");
        }

        $record = $this->toRecord($data, $record);
        $this->entityManager->flush();

        return $this->toDomain($record);
    }

    public function delete(string $id): void
    {
        $record = $this->entityManager->find(MessageRecord::class, $id);

        if ($record !== null) {
            $this->entityManager->remove($record);
            $this->entityManager->flush();
        }
    }

    public function findById(string $id): mixed
    {
        $record = $this->entityManager->find(MessageRecord::class, $id);

        return $record !== null ? $this->toDomain($record) : null;
    }

    public function findByExternalId(string $monitorId, string $externalId): ?Message
    {
        $record = $this->entityManager->getRepository(MessageRecord::class)->findOneBy([
            'monitorId' => $monitorId,
            'externalId' => $externalId,
        ]);

        return $record !== null ? $this->toDomain($record) : null;
    }

    public function deleteByMonitorId(string $monitorId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(MessageRecord::class, 'm')
            ->where('m.monitorId = :monitorId')
            ->setParameter('monitorId', $monitorId)
            ->getQuery()
            ->execute();
    }

    public function deleteByLabel(string $monitorId, string $label): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->delete(MessageRecord::class, 'm')
            ->where('m.monitorId = :monitorId')
            ->andWhere('m.label = :label')
            ->setParameter('monitorId', $monitorId)
            ->setParameter('label', $label)
            ->getQuery()
            ->execute();
    }

    public function countByLabel(string $monitorId): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m.label AS label, COUNT(m.id) AS total')
            ->from(MessageRecord::class, 'm')
            ->where('m.monitorId = :monitorId')
            ->setParameter('monitorId', $monitorId)
            ->groupBy('m.label')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) ($row['label'] ?? '')] = (int) $row['total'];
        }

        return $counts;
    }

    public function findPage(mixed $params): PageResult
    {
        /** @var MessagePageParams $params */
        $page = max($params->page, 1);
        $perPage = max($params->perPage, 1);

        $query = $this->entityManager->createQueryBuilder()
            ->from(MessageRecord::class, 'm')
            ->where('m.monitorId = :monitorId')
            ->setParameter('monitorId', $params->monitorId);

        if ($params->labels !== []) {
            $query->andWhere('m.label IN (:labels)')->setParameter('labels', $params->labels);
        }

        $total = (int) (clone $query)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();

        $records = $query->select('m')
            ->orderBy('m.receivedAt', 'DESC')
            ->addOrderBy('m.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new PageResult(array_map($this->toDomain(...), $records), $page, $perPage, $total);
    }

    private function toRecord(Message $message, ?MessageRecord $record = null): MessageRecord
    {
        $record ??= new MessageRecord();
        $record->id = $message->getId();
        $record->monitorId = $message->getMonitorId();
        $record->externalId = $message->getExternalId();
        $record->authorName = $message->getAuthorName();
        $record->text = $message->getText();
        $record->receivedAt = $message->getReceivedAt();
        $record->label = $message->getLabel();
        $record->labelProbabilities = $message->getLabelProbabilities();
        $record->translatedText = $message->getTranslatedText();
        $record->classifiedAt = $message->getClassifiedAt();
        $record->createdAt = $message->getCreatedAt();
        $record->updatedAt = $message->getUpdatedAt();
        $record->deletedAt = $message->getDeletedAt();

        return $record;
    }

    private function toDomain(MessageRecord $record): Message
    {
        return new Message([
            'id' => $record->id,
            'monitorId' => $record->monitorId,
            'externalId' => $record->externalId,
            'authorName' => $record->authorName,
            'text' => $record->text,
            'receivedAt' => $record->receivedAt,
            'label' => $record->label,
            'labelProbabilities' => $record->labelProbabilities,
            'translatedText' => $record->translatedText,
            'classifiedAt' => $record->classifiedAt,
            'createdAt' => $record->createdAt,
            'updatedAt' => $record->updatedAt,
            'deletedAt' => $record->deletedAt,
        ]);
    }
}
