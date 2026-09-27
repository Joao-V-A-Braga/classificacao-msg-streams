<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\User\Provider;

final class UserPageParams
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }
}
