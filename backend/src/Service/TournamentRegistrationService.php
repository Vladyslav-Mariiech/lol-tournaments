<?php

namespace App\Service;

use App\Entity\TournamentRegistration;
use App\Entity\User;
use App\Repository\TournamentRegistrationRepository;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TournamentRegistrationService
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly TournamentRegistrationRepository $registrationRepository,
        private readonly UserAccountService $accountService,
        private readonly RiotApiService $riotApiService,
        private readonly EntityManagerInterface $em
    ) {}

    public function registerUser(User $user, int $tournamentId, array $payload): TournamentRegistration
    {
        $tournament = $this->tournamentRepository->find($tournamentId);
        if (!$tournament) {
            throw new NotFoundHttpException('Турнир не найден');
        }

        if ($tournament->getStatus() !== 'registration') {
            throw new BadRequestHttpException('Регистрация на этот турнир закрыта');
        }

        $existing = $this->registrationRepository->findOneBy([
            'tournament' => $tournament,
            'user' => $user,
        ]);

        if ($existing) {
            throw new BadRequestHttpException('Вы уже зарегистрированы на этот турнир');
        }

        $accountId = $payload['accountId'] ?? null;
        $riotId = trim($payload['riotId'] ?? '');
        $region = trim($payload['region'] ?? 'euw1');

        if ($accountId) {
            $selectedAccount = null;
            foreach ($user->getLolAccounts() as $acc) {
                if ($acc->getId() === (int)$accountId && !$acc->isUnlinked()) {
                    $selectedAccount = $acc;
                    break;
                }
            }

            if (!$selectedAccount) {
                throw new BadRequestHttpException('Выбранный аккаунт не найден или отвязан');
            }

            $lolRank = $selectedAccount->getLolRank();
            $links = $this->riotApiService->buildExternalLinks($selectedAccount->getRiotId(), $selectedAccount->getRegion());
        } else {
            if (empty($riotId)) {
                throw new BadRequestHttpException('Укажите ваш Riot ID или выберите привязанный аккаунт');
            }

            $account = $this->accountService->linkAccount($user, $riotId, $region);
            $lolRank = $account->getLolRank();
            $links = $this->riotApiService->buildExternalLinks($account->getRiotId(), $region);
        }

        $registration = new TournamentRegistration();
        $registration->setTournament($tournament);
        $registration->setUser($user);
        $registration->setLolRank($lolRank);
        $registration->setOpggUrl($links['opgg']);
        $registration->setLeagueofgraphsUrl($links['leagueOfGraphs']);
        $registration->setDpmUrl($links['dpm']);
        $registration->setIsCaptainWilling((bool)($payload['isCaptainWilling'] ?? false));

        $this->em->persist($registration);
        $this->em->flush();

        return $registration;
    }
}
