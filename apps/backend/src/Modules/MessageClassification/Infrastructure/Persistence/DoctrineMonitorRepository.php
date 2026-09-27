<?php

declare(strict_types=1);

namespace App\Modules\MessageClassification\Infrastructure\Persistence;

use App\Modules\MessageClassification\Infrastructure\Persistence\Doctrine\MonitorRecord;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorPageParams;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Db\PageResult;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineMonitorRepository implements MonitorRepositoryInterface
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
        $record = $this->entityManager->find(MonitorRecord::class, $data->getId());

        if ($record === null) {
            throw new \RuntimeException("Monitor {$data->getId()} not found.");
        }

        $record = $this->toRecord($data, $record);
        $this->entityManager->flush();

        return $this->toDomain($record);
    }

    public function delete(string $id): void
    {
        $record = $this->entityManager->find(MonitorRecord::class, $id);

        if ($record !== null) {
            $this->entityManager->remove($record);
            $this->entityManager->flush();
        }
    }

    public function findById(string $id): mixed
    {
        $record = $this->entityManager->find(MonitorRecord::class, $id);

        return $record !== null ? $this->toDomain($record) : null;
    }

    public function findRunning(): array
    {
        $records = $this->entityManager->getRepository(MonitorRecord::class)->findBy(
            ['status' => Monitor::STATUS_RUNNING],
            ['startedAt' => 'ASC'],
        );

        return array_map($this->toDomain(...), $records);
    }

    public function findPage(mixed $params): PageResult
    {
        /** @var MonitorPageParams $params */
        $repository = $this->entityManager->getRepository(MonitorRecord::class);
        $page = max($params->page, 1);
        $perPage = max($params->perPage, 1);

        $records = $repository->createQueryBuilder('m')
            ->where('m.userId = :userId')
            ->setParameter('userId', $params->userId)
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $total = (int) $repository->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.userId = :userId')
            ->setParameter('userId', $params->userId)
            ->getQuery()
            ->getSingleScalarResult();

        return new PageResult(array_map($this->toDomain(...), $records), $page, $perPage, $total);
    }

    private function toRecord(Monitor $monitor, ?MonitorRecord $record = null): MonitorRecord
    {
        $record ??= new MonitorRecord();
        $record->id = $monitor->getId();
        $record->userId = $monitor->getUserId();
        $record->name = $monitor->getName();
        $record->sourceType = $monitor->getSourceType();
        $record->url = $monitor->getUrl();
        $record->status = $monitor->getStatus();
        $record->tags = array_map(static fn (MonitorTag $tag): array => $tag->toArray(), $monitor->getTags());
        $record->startedAt = $monitor->getStartedAt();
        $record->includeExistingComments = $monitor->includesExistingComments();
        $record->translateForLaya = $monitor->translatesForLaya();
        $record->runnerState = $monitor->getRunnerState();
        $record->runnerDetail = $monitor->getRunnerDetail();
        $record->createdAt = $monitor->getCreatedAt();
        $record->updatedAt = $monitor->getUpdatedAt();
        $record->deletedAt = $monitor->getDeletedAt();

        return $record;
    }

    private function toDomain(MonitorRecord $record): Monitor
    {
        return new Monitor([
            'id' => $record->id,
            'userId' => $record->userId,
            'name' => $record->name,
            'sourceType' => $record->sourceType,
            'url' => $record->url,
            'status' => $record->status,
            'tags' => array_map(MonitorTag::fromArray(...), $record->tags),
            'startedAt' => $record->startedAt,
            'includeExistingComments' => $record->includeExistingComments,
            'translateForLaya' => $record->translateForLaya,
            'runnerState' => $record->runnerState,
            'runnerDetail' => $record->runnerDetail,
            'createdAt' => $record->createdAt,
            'updatedAt' => $record->updatedAt,
            'deletedAt' => $record->deletedAt,
        ]);
    }
}
