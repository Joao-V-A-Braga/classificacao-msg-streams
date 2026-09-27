<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\User\Provider;

interface JwtProviderInterface
{
    /**
     * @param array<string, mixed> $claims
     */
    public function generate(array $claims): string;

    /**
     * @return array<string, mixed>
     * @throws \RuntimeException quando o token for invalido ou estiver expirado
     */
    public function verify(string $token): array;
}
