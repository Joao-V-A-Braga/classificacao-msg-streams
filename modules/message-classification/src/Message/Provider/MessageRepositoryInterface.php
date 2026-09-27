<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Message\Provider;

use ClassificacaoMsgStreams\Shared\Db\CrudRepositoryInterface;
use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;

/**
 * @template-extends CrudRepositoryInterface<Message, Message, Message, MessagePageParams>
 */
interface MessageRepositoryInterface extends CrudRepositoryInterface
{
    public function findByExternalId(string $monitorId, string $externalId): ?Message;

    public function deleteByMonitorId(string $monitorId): void;

    /**
     * Deletes the messages of the monitor classified with `$label`; returns how many were deleted.
     */
    public function deleteByLabel(string $monitorId, string $label): int;

    /**
     * @return array<string, int> number of messages of the monitor per label ('' = not classified yet)
     */
    public function countByLabel(string $monitorId): array;
}
