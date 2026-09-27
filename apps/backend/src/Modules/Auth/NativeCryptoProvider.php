<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use ClassificacaoMsgStreams\Auth\User\Provider\CryptoProviderInterface;

final class NativeCryptoProvider implements CryptoProviderInterface
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }
}
