<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\Provider;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\Shared\Db\CrudRepositoryInterface;

/**
 * @template-extends CrudRepositoryInterface<Monitor, Monitor, Monitor, MonitorPageParams>
 */
interface MonitorRepositoryInterface extends CrudRepositoryInterface
{
    /**
     * Every running monitor, of every user — what the ingestion runner must be watching.
     *
     * @return Monitor[]
     */
    public function findRunning(): array;
}
