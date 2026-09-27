<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Db;

interface DeleteRepositoryInterface
{
    public function delete(string $id): void;
}
