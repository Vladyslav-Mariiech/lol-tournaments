<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DiscordController extends AbstractController
{
    /**
     * Перенаправляет пользователя на форму авторизации Discord.
     */
    #[Route('/api/auth/discord', name: 'connect_discord_start', methods: ['GET'])]
    public function connect(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('discord')
            ->redirect(['identify'], []);
    }

    /**
     * Точка возврата после авторизации в Discord.
     * Сюда Discord отправляет пользователя с кодом авторизации.
     */
    #[Route('/api/auth/discord/check', name: 'connect_discord_check', methods: ['GET'])]
    public function connectCheck(): Response
    {
        // Метод перехватывается DiscordAuthenticator, поэтому остается пустым.
        return new Response();
    }
}
