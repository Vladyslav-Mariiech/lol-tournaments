<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class AuthController extends AbstractController
{
    /**
     * Возвращает информацию о текущем авторизованном пользователе.
     */
    #[Route('/api/auth/me', name: 'app_auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'authenticated' => false,
            ], 401);
        }

        return $this->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->getId(),
                'discordId' => $user->getDiscordId(),
                'username' => $user->getUsername(),
                'avatar' => $user->getAvatar(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    /**
     * Точка выхода из системы (перехватывается файрволом Symfony).
     */
    #[Route('/api/auth/logout', name: 'app_auth_logout', methods: ['GET', 'POST'])]
    public function logout(): void
    {
        throw new \LogicException('Этот метод перехватывается ключом logout в security.yaml');
    }
}
