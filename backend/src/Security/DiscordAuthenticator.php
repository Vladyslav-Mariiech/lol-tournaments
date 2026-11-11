<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;

class DiscordAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
        private readonly string $frontendUrl
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Аутентификатор срабатывает только на маршруте /api/auth/discord/check
        return $request->attributes->get('_route') === 'connect_discord_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('discord');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var DiscordResourceOwner $discordUser */
                $discordUser = $client->fetchUserFromToken($accessToken);

                $discordId = (string) $discordUser->getId();

                // Ищем пользователя по discordId
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['discordId' => $discordId]);

                if (!$user) {
                    $user = new User();
                    $user->setDiscordId($discordId);
                }

                // Актуализируем никнейм и хэш аватара при каждом входе
                $user->setUsername($discordUser->getUsername());
                $user->setAvatar($discordUser->getAvatarHash());

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // После успешного входа перенаправляем пользователя на Vue-клиент
        return new RedirectResponse($this->frontendUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // В случае ошибки возвращаем на Vue с параметром ошибки
        return new RedirectResponse($this->frontendUrl . '?error=auth_failed');
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // Если анонимный пользователь пытается зайти на защищённый роут, отправляем его на старт Discord OAuth2
        return new RedirectResponse($this->router->generate('connect_discord_start'));
    }
}
