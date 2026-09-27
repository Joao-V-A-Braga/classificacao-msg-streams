<?php

declare(strict_types=1);

namespace App\Shared\Security;

/**
 * Value object leve em torno do payload bruto retornado por
 * JwtProviderInterface::verify(). Não assume nenhum campo além de `sub`
 * (identificador do sujeito do token), já que o payload pode evoluir por
 * projeto/módulo.
 */
final class JwtPayload
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(private readonly array $claims)
    {
    }

    public function subject(): string
    {
        return (string) ($this->claims['sub'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function claims(): array
    {
        return $this->claims;
    }

    public function get(string $claim): mixed
    {
        return $this->claims[$claim] ?? null;
    }
}
