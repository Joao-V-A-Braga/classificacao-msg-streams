<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\Tests\Mock;

use ClassificacaoMsgStreams\Auth\User\Provider\JwtProviderInterface;

final class FakeJwtProvider implements JwtProviderInterface
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastClaims = null;

    public int $generateCalls = 0;

    public function generate(array $claims): string
    {
        $this->generateCalls++;
        $this->lastClaims = $claims;

        return 'fake-token.' . base64_encode((string) json_encode($claims));
    }

    public function verify(string $token): array
    {
        $encoded = substr($token, strlen('fake-token.'));
        $decoded = json_decode(base64_decode($encoded), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid fake token.');
        }

        return $decoded;
    }
}
