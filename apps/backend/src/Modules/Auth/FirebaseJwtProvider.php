<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use ClassificacaoMsgStreams\Auth\User\Provider\JwtProviderInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class FirebaseJwtProvider implements JwtProviderInterface
{
    private const ALGO = 'HS256';
    private const TTL_SECONDS = 3600;

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $secret,
    ) {
    }

    public function generate(array $claims): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + self::TTL_SECONDS,
        ]);

        return JWT::encode($payload, $this->secret, self::ALGO);
    }

    public function verify(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, self::ALGO));
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Invalid or expired token.', 0, $exception);
        }

        return (array) $decoded;
    }
}
