<?php

namespace App\Controller;

use App\Entity\Tournament;
use App\Entity\TournamentRegistration;
use App\Entity\User;
use App\Repository\TournamentRepository;
use App\Repository\TournamentRegistrationRepository;
use App\Service\RiotApiService;
use App\Service\TournamentRegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        TournamentRegistrationService $registrationService,
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if (!$user) {
            return $this->json(['error' => 'Необходимо авторизоваться'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $registration = $registrationService->registerUser($user, $id, $payload);

            return $this->json([
                'message' => 'Заявка успешно принята!',
                'lolRank' => $registration->getLolRank(),
            ], Response::HTTP_CREATED);
        } catch (NotFoundHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/registrations', name: 'app_admin_tournament_registrations', methods: ['GET'])]
    public function getRegistrations(
        int $id,
        TournamentRepository $tournamentRepository,
        TournamentRegistrationRepository $registrationRepository
    ): JsonResponse {
        $tournament = $tournamentRepository->find($id);

        if (!$tournament) {
            return $this->json(['error' => 'Турнир не найден'], Response::HTTP_NOT_FOUND);
        }

        $registrations = $registrationRepository->findBy(
            ['tournament' => $tournament],
            ['registeredAt' => 'ASC']
        );

        $data = array_map(static function (TournamentRegistration $reg) {
            $user = $reg->getUser();
            return [
                'id' => $reg->getId(),
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'avatar' => $user->getAvatar(),
                    'discordId' => $user->getDiscordId(),
                ],
                'lolRank' => $reg->getLolRank(),
                'isCaptainWilling' => $reg->isCaptainWilling(),
                'links' => [
                    'opgg' => $reg->getOpggUrl(),
                    'leagueOfGraphs' => $reg->getLeagueofgraphsUrl(),
                    'dpm' => $reg->getDpmUrl(),
                ],
                'registeredAt' => $reg->getRegisteredAt()?->format(\DateTimeInterface::ATOM),
            ];
        }, $registrations);

        return $this->json([
            'tournament' => [
                'id' => $tournament->getId(),
                'name' => $tournament->getName(),
                'maxTeams' => $tournament->getMaxTeams(),
            ],
            'totalRegistrations' => count($data),
            'registrations' => $data,
        ]);
    }
}
