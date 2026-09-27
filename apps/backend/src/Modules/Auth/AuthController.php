<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Auth\Infrastructure\Persistence\DoctrineUserRepository;
use App\Shared\Attribute\IsPublic;
use App\Shared\Security\AuthenticatedUser;
use ClassificacaoMsgStreams\Auth\User\Model\User;
use ClassificacaoMsgStreams\Auth\User\UseCase\LoginUser;
use ClassificacaoMsgStreams\Auth\User\UseCase\LoginUserIn;
use ClassificacaoMsgStreams\Auth\User\UseCase\RegisterUser;
use ClassificacaoMsgStreams\Auth\User\UseCase\RegisterUserIn;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly DoctrineUserRepository $userRepository,
        private readonly NativeCryptoProvider $cryptoProvider,
        private readonly FirebaseJwtProvider $jwtProvider,
    ) {
    }

    #[IsPublic]
    #[Route('/register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterUserIn $input): JsonResponse
    {
        $useCase = new RegisterUser($this->userRepository, $this->cryptoProvider);
        $output = $useCase->execute($input);

        return $this->json(['user' => $this->serializeUser($output->user)], Response::HTTP_CREATED);
    }

    #[IsPublic]
    #[Route('/login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginUserIn $input): JsonResponse
    {
        $useCase = new LoginUser($this->userRepository, $this->cryptoProvider, $this->jwtProvider);
        $output = $useCase->execute($input);

        return $this->json([
            'token' => $output->token,
            'user' => $this->serializeUser($output->user),
        ]);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(#[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $found = $this->userRepository->findById($user->getUserIdentifier());
        if ($found === null) {
            throw new NotFoundError('user.not_found');
        }

        return $this->json(['user' => $this->serializeUser($found)]);
    }

    /**
     * Adaptação de shape exigida pelo contexto HTTP: nunca expor o hash de
     * senha do usuário na resposta da API.
     *
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
