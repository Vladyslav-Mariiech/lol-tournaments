<?php

namespace App\Controller;

use App\Entity\Tournament;
use App\Entity\TournamentRegistration;
use App\Entity\User;
use App\Repository\TournamentRepository;
use App\Repository\TournamentRegistrationRepository;
use App\Service\RiotApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tournaments')]
class TournamentController extends AbstractController
{
    /**
     * Публичный эндпоинт: просмотр списка турниров (доступен всем авторизованным юзерам)
     */
    #[Route('', name: 'api_tournaments_list', methods: ['GET'])]
    public function list(TournamentRepository $tournamentRepository): JsonResponse
    {
        $tournaments = $tournamentRepository->findBy([], ['createdAt' => 'DESC']);

        $data = array_map(fn(Tournament $t) => [
            'id' => $t->getId(),
            'name' => $t->getName(),
            'format' => $t->getFormat(),
            'status' => $t->getStatus(),
            'maxTeams' => $t->getMaxTeams(),
            'startDate' => $t->getStartDate()?->format('Y-m-d H:i'),
        ], $tournaments);

        return $this->json($data);
    }

    /**
     * Защищенный эндпоинт: доступен ТОЛЬКО администраторам
     */
    #[Route('', name: 'api_tournaments_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (empty($payload['name'])) {
            return $this->json(['error' => 'Название турнира обязательно'], Response::HTTP_BAD_REQUEST);
        }

        $tournament = new Tournament();
        $tournament->setName($payload['name']);
        $tournament->setFormat($payload['format'] ?? '5v5');
        $tournament->setMaxTeams((int)($payload['maxTeams'] ?? 16));

        if (!empty($payload['startDate'])) {
            $tournament->setStartDate(new \DateTime($payload['startDate']));
        }

        $em->persist($tournament);
        $em->flush();

        return $this->json([
            'message' => 'Турнир успешно создан',
            'id' => $tournament->getId()
        ], Response::HTTP_CREATED);
    }

    /**
     * Эндпоинт регистрации участника на турнир по Riot ID
     */
    #[Route('/{id}/register', name: 'api_tournaments_register', methods: ['POST'])]
    public function register(
        int $id,
        Request $request,
        TournamentRepository $tournamentRepository,
        TournamentRegistrationRepository $registrationRepository,
        RiotApiService $riotApiService,
        EntityManagerInterface $em,
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if (!$user) {
            return $this->json(['error' => 'Необходимо авторизоваться'], Response::HTTP_UNAUTHORIZED);
        }

        $tournament = $tournamentRepository->find($id);
        if (!$tournament) {
            return $this->json(['error' => 'Турнир не найден'], Response::HTTP_NOT_FOUND);
        }

        if ($tournament->getStatus() !== 'registration') {
            return $this->json(['error' => 'Регистрация на этот турнир закрыта'], Response::HTTP_BAD_REQUEST);
        }

        // Проверка повторной регистрации
        $existing = $registrationRepository->findOneBy([
            'tournament' => $tournament,
            'user' => $user,
        ]);

        if ($existing) {
            return $this->json(['error' => 'Вы уже зарегистрированы на этот турнир'], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $riotId = trim($payload['riotId'] ?? '');
        $region = trim($payload['region'] ?? 'euw1'); // По умолчанию EUW


        if (empty($riotId)) {
            return $this->json(['error' => 'Укажите ваш Riot ID (например, Name#TAG)'], Response::HTTP_BAD_REQUEST);
        }

        // Запрос к Riot API для проверки игрока и получения ранга
        $riotData = $riotApiService->getPlayerData($riotId, $region);
        $riotData = $riotApiService->getPlayerData($riotId, $region);
        if (!$riotData) {
            $regionName = strtoupper(str_replace('1', '', $region));
            return $this->json([
                'error' => sprintf('Игрок с таким Riot ID не найден на сервере %s', $regionName)
            ], Response::HTTP_BAD_REQUEST);
        }

        $registration = new TournamentRegistration();
        $registration->setTournament($tournament);
        $registration->setUser($user);
        $registration->setLolRank($riotData['rankDisplay']);
        $registration->setOpggUrl($riotData['externalLinks']['opgg']);
        $registration->setLeagueofgraphsUrl($riotData['externalLinks']['leagueOfGraphs']);
        $registration->setDpmUrl($riotData['externalLinks']['dpm']);
        $registration->setIsCaptainWilling((bool)($payload['isCaptainWilling'] ?? false));

        $em->persist($registration);
        $em->flush();

        return $this->json([
            'message' => 'Заявка успешно принята!',
            'verifiedData' => $riotData,
        ], Response::HTTP_CREATED);
    }
}
