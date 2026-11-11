<?php

namespace App\Service;

use App\Entity\AccountAuditLog;
use App\Entity\User;
use App\Entity\UserLolAccount;
use App\Repository\UserLolAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UserAccountService
{
    public function __construct(
        private readonly RiotApiService $riotApiService,
        private readonly UserLolAccountRepository $accountRepository,
        private readonly EntityManagerInterface $em
    ) {}

    /**
     * Привязка нового Riot ID к профилю пользователя
     */
    public function linkAccount(User $user, string $riotId, string $region): UserLolAccount
    {
        $riotData = $this->riotApiService->getPlayerData($riotId, $region);
        if (!$riotData) {
            throw new BadRequestHttpException('Игрок с таким Riot ID не найден на указанном сервере');
        }

        $puuid = $riotData['puuid'];

        // Проверяем, не привязан ли этот Riot ID уже к кому-то в активном статусе
        $existing = $this->accountRepository->findOneBy([
            'puuid' => $puuid,
            'isUnlinked' => false
        ]);

        if ($existing) {
            if ($existing->getUser()->getId() === $user->getId()) {
                throw new BadRequestHttpException('Этот аккаунт уже привязан к вашему профилю');
            }
            throw new BadRequestHttpException('Этот Riot ID уже привязан к другому пользователю');
        }

        // Проверяем, есть ли у пользователя другие активные аккаунты (если нет — сделаем этот основным)
        $hasActiveAccounts = count(array_filter(
                $user->getLolAccounts()->toArray(),
                fn(UserLolAccount $a) => !$a->isUnlinked()
            )) > 0;

        $account = new UserLolAccount();
        $account->setUser($user);
        $account->setPuuid($puuid);
        $account->setGameName($riotData['gameName']);
        $account->setTagLine($riotData['tagLine']);
        $account->setRegion($region);
        $account->setLolRank($riotData['rankDisplay']);
        $account->setIsPrimary(!$hasActiveAccounts);

        $this->em->persist($account);

        // Фиксируем запись в аудит-логе
        $this->logAction($user, 'LINK', [
            'puuid' => $puuid,
            'riotId' => $account->getRiotId(),
            'region' => $region,
            'rank' => $account->getLolRank(),
        ]);

        $this->em->flush();

        return $account;
    }

    /**
     * Отвязка Riot-аккаунта от профиля (сохраняем запись, но ставим isUnlinked = true)
     */
    public function unlinkAccount(User $user, int $accountId): void
    {
        $account = $this->accountRepository->find($accountId);

        if (!$account || $account->getUser()->getId() !== $user->getId()) {
            throw new BadRequestHttpException('Аккаунт не найден');
        }

        if ($account->isUnlinked()) {
            throw new BadRequestHttpException('Аккаунт уже отвязан');
        }

        $account->setIsUnlinked(true);
        $wasPrimary = $account->isPrimary();
        $account->setIsPrimary(false);

        // Если отвязали основной аккаунт, назначаем основным следующий доступный активный
        if ($wasPrimary) {
            foreach ($user->getLolAccounts() as $remaining) {
                if (!$remaining->isUnlinked() && $remaining->getId() !== $account->getId()) {
                    $remaining->setIsPrimary(true);
                    break;
                }
            }
        }

        $this->logAction($user, 'UNLINK', [
            'accountId' => $account->getId(),
            'riotId' => $account->getRiotId(),
            'puuid' => $account->getPuuid(),
        ]);

        $this->em->flush();
    }

    /**
     * Установка аккаунта в качестве основного (Primary)
     */
    public function setPrimaryAccount(User $user, int $accountId): void
    {
        $targetAccount = $this->accountRepository->find($accountId);

        if (!$targetAccount || $targetAccount->getUser()->getId() !== $user->getId()) {
            throw new BadRequestHttpException('Аккаунт не найден');
        }

        if ($targetAccount->isUnlinked()) {
            throw new BadRequestHttpException('Нельзя сделать основным отвязанный аккаунт');
        }

        foreach ($user->getLolAccounts() as $acc) {
            $acc->setIsPrimary($acc->getId() === $targetAccount->getId());
        }

        $this->logAction($user, 'SET_PRIMARY', [
            'accountId' => $targetAccount->getId(),
            'riotId' => $targetAccount->getRiotId(),
        ]);

        $this->em->flush();
    }

    private function logAction(User $user, string $action, array $details): void
    {
        $log = new AccountAuditLog();
        $log->setUser($user);
        $log->setAction($action);
        $log->setDetails($details);
        $this->em->persist($log);
    }
}
