<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserLolAccount;
use App\Service\UserAccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/user')]
class UserController extends AbstractController
{
    /**
     * Список привязанных Riot-аккаунтов текущего пользователя
     */
    #[Route('/accounts', name: 'api_user_accounts', methods: ['GET'])]
    public function getAccounts(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Необходимо авторизоваться'], Response::HTTP_UNAUTHORIZED);
        }

        $accounts = array_map(static function (UserLolAccount $acc) {
            return [
                'id' => $acc->getId(),
                'puuid' => $acc->getPuuid(),
                'riotId' => $acc->getRiotId(),
                'gameName' => $acc->getGameName(),
                'tagLine' => $acc->getTagLine(),
                'region' => $acc->getRegion(),
                'lolRank' => $acc->getLolRank(),
                'isPrimary' => $acc->isPrimary(),
                'isUnlinked' => $acc->isUnlinked(),
                'createdAt' => $acc->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }, $user->getLolAccounts()->toArray());

        return $this->json([
            'accounts' => array_values($accounts)
        ]);
    }

    /**
     * Привязать новый Riot ID
     */
    #[Route('/accounts/link', name: 'api_user_accounts_link', methods: ['POST'])]
    public function linkAccount(
        Request $request,
        UserAccountService $accountService,
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if (!$user) {
            return $this->json(['error' => 'Необходимо авторизоваться'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $riotId = trim($payload['riotId'] ?? '');
        $region = trim($payload['region'] ?? 'euw1');

        if (empty($riotId)) {
            return $this->json(['error' => 'Укажите ваш Riot ID (например, Name#TAG)'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $account = $accountService->linkAccount($user, $riotId, $region);

            return $this->json([
                'message' => 'Аккаунт успешно привязан',
                'account' => [
                    'id' => $account->getId(),
                    'riotId' => $account->getRiotId(),
                    'region' => $account->getRegion(),
                    'lolRank' => $account->getLolRank(),
                    'isPrimary' => $account->isPrimary(),
                ]
            ], Response::HTTP_CREATED);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Отвязать Riot ID
     */
    #[Route('/accounts/{id}/unlink', name: 'api_user_accounts_unlink', methods: ['POST'])]
    public function unlinkAccount(
        int $id,
        UserAccountService $accountService,
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if (!$user) {
            return $this->json(['error' => 'Необходимо авторизоваться'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $accountService->unlinkAccount($user, $id);
            return $this->json(['message' => 'Аккаунт успешно отвязан']);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Сделать аккаунт основным (Primary)
     */
    #[Route('/accounts/{id}/primary', name: 'api_user_accounts_primary', methods: ['POST'])]
    public function setPrimary(
        int $id,
        UserAccountService $accountService,
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if (!$user) {
            return $this->json(['error' => 'Необходимо авторизоваться'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $accountService->setPrimaryAccount($user, $id);
            return $this->json(['message' => 'Основной аккаунт обновлен']);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
