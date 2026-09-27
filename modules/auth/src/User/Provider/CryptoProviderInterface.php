<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\User\Provider;

interface CryptoProviderInterface
{
    public function hash(string $plainPassword): string;

    public function verify(string $plainPassword, string $hashedPassword): bool;
}
