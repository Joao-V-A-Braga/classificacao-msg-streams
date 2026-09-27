<?php

declare(strict_types=1);

namespace App\Shared\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Representa o usuário autenticado a partir do payload do JWT, para uso via
 * #[CurrentUser] nativo do Symfony Security nos controllers.
 *
 * Deliberadamente NÃO é a entidade de domínio completa: mantém o domínio
 * puro e desacoplado do framework. Quando um controller precisar da entidade
 * completa, deve buscá-la explicitamente via repositório usando
 * getUserIdentifier() (o `sub` do token).
 */
final class AuthenticatedUser implements UserInterface
{
    private readonly JwtPayload $payload;

    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(array $claims)
    {
        $this->payload = new JwtPayload($claims);
    }

    public function getUserIdentifier(): string
    {
        return $this->payload->subject();
    }

    public function getClaims(): JwtPayload
    {
        return $this->payload;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Nada de sensível é mantido além do payload já validado.
    }
}
