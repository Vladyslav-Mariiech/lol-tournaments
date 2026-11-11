<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class DevController extends AbstractController
{
    #[Route('/api/dev/toggle-admin', name: 'app_dev_toggle_admin', methods: ['POST'])]
    public function toggleAdmin(#[CurrentUser] ?User $user, EntityManagerInterface $em): JsonResponse
    {
        // Защита: эндпоинт работает только в окружении разработки (dev)
        if ($this->getParameter('kernel.environment') !== 'dev') {
            return $this->json(['error' => 'Доступно только в dev-режиме'], 403);
        }

        if (!$user) {
            return $this->json(['error' => 'Пользователь не авторизован'], 401);
        }

        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            // Убираем роль админа
            $roles = array_values(array_filter($roles, fn($r) => $r !== 'ROLE_ADMIN'));
            $isAdminNow = false;
        } else {
            // Добавляем роль админа
            $roles[] = 'ROLE_ADMIN';
            $roles = array_unique($roles);
            $isAdminNow = true;
        }

        $user->setRoles($roles);
        $em->flush();

        return $this->json([
            'message' => $isAdminNow ? 'Права ROLE_ADMIN выданы' : 'Права ROLE_ADMIN сняты',
            'isAdmin' => $isAdminNow,
            'roles' => $user->getRoles(),
        ]);
    }
}
