<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\MatchValorantRepository;
use App\Service\ValorantTracker\TrackerGgClient;
use App\Service\ValorantTracker\ValorantTrackerSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/valorant-tracker')]
class ValorantTrackerController extends AbstractController
{
    #[Route('', name: 'valorant_tracker_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, MatchValorantRepository $matchRepository): Response
    {
        $user = $this->getSecureUser();

        $filters = [
            'player' => $request->query->getString('player'),
            'team' => $request->query->getString('team'),
            'match' => $request->query->getString('match'),
            'archived' => $request->query->getString('archived'),
        ];

        $matches = $matchRepository->searchForDashboard($user, $filters);

        return $this->render('valorant_tracker/dashboard.html.twig', [
            'matches' => $matches,
            'filters' => $filters,
            'trackerProfile' => $this->getTrackerProfile($request),
        ]);
    }

    #[Route('/import', name: 'valorant_tracker_import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        TrackerGgClient $trackerClient,
        ValorantTrackerSyncService $syncService,
    ): Response {
        $user = $this->getSecureUser();

        $profile = $this->getTrackerProfile($request);
        $matchesFromApi = [];
        $selectedMatchId = '';

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('valorant_tracker_import', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $profile['riotName'] = trim((string) $request->request->get('riotName', ''));
            $profile['riotTag'] = trim((string) $request->request->get('riotTag', ''));
            $profile['region'] = trim((string) $request->request->get('region', 'eu')) ?: 'eu';
            $selectedMatchId = trim((string) $request->request->get('selectedMatchId', ''));

            if ($profile['riotName'] === '' || $profile['riotTag'] === '') {
                $this->addFlash('error', 'Riot ID requis: renseignez le pseudo et le tag.');
            } else {
                try {
                    $matchesFromApi = $trackerClient->fetchMatches($profile['riotName'], $profile['riotTag'], $profile['region']);
                    $this->saveTrackerProfile($request, $profile['riotName'], $profile['riotTag'], $profile['region']);

                    $action = (string) $request->request->get('action', 'load');
                    if ($action === 'import') {
                        if ($selectedMatchId === '') {
                            $this->addFlash('error', 'Sélectionnez un match à importer.');
                        } else {
                            $result = $syncService->syncMatches(
                                $user,
                                $profile['riotName'],
                                $profile['riotTag'],
                                $profile['region'],
                                $selectedMatchId
                            );
                            $this->addFlash('success', sprintf('Import terminé: %d nouveau(x), %d mis à jour.', $result['imported'], $result['updated']));

                            return $this->redirectToRoute('valorant_tracker_dashboard');
                        }
                    } elseif ($action === 'import-latest') {
                        $result = $syncService->syncMatches($user, $profile['riotName'], $profile['riotTag'], $profile['region']);
                        $this->addFlash('success', sprintf('Synchronisation terminée: %d nouveau(x), %d mis à jour.', $result['imported'], $result['updated']));
                        return $this->redirectToRoute('valorant_tracker_dashboard');
                    }
                } catch (\Throwable $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
        }

        return $this->render('valorant_tracker/import.html.twig', [
            'profile' => $profile,
            'apiMatches' => $matchesFromApi,
            'selectedMatchId' => $selectedMatchId,
        ]);
    }

    #[Route('/match/{id}', name: 'valorant_tracker_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, MatchValorantRepository $repository): Response
    {
        $user = $this->getSecureUser();
        $match = $repository->findOwnedById($user, $id);

        if (!$match) {
            throw $this->createNotFoundException('Match introuvable.');
        }

        return $this->render('valorant_tracker/show.html.twig', [
            'match' => $match,
        ]);
    }

    #[Route('/refresh', name: 'valorant_tracker_refresh', methods: ['POST'])]
    public function refresh(Request $request, ValorantTrackerSyncService $syncService): RedirectResponse
    {
        $user = $this->getSecureUser();

        if (!$this->isCsrfTokenValid('valorant_tracker_refresh', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $profile = $this->getTrackerProfile($request);
        if ($profile['riotName'] === '' || $profile['riotTag'] === '') {
            $this->addFlash('error', 'Aucun profil Tracker sauvegardé. Lancez d’abord un import.');
            return $this->redirectToRoute('valorant_tracker_import');
        }

        try {
            $result = $syncService->syncMatches($user, $profile['riotName'], $profile['riotTag'], $profile['region']);
            $this->addFlash('success', sprintf('Rafraîchi: %d nouveau(x), %d mis à jour.', $result['imported'], $result['updated']));
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('valorant_tracker_dashboard');
    }

    #[Route('/match/{id}/archive', name: 'valorant_tracker_archive', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function archive(int $id, Request $request, MatchValorantRepository $repository, ValorantTrackerSyncService $syncService): RedirectResponse
    {
        $user = $this->getSecureUser();
        if (!$this->isCsrfTokenValid('valorant_tracker_archive_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $match = $repository->findOwnedById($user, $id);
        if (!$match) {
            throw $this->createNotFoundException('Match introuvable.');
        }

        $syncService->archive($match);
        $this->addFlash('success', 'Match archivé avec succès.');

        return $this->redirectToRoute('valorant_tracker_dashboard');
    }

    #[Route('/match/{id}/delete', name: 'valorant_tracker_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, MatchValorantRepository $repository, ValorantTrackerSyncService $syncService): RedirectResponse
    {
        $user = $this->getSecureUser();
        if (!$this->isCsrfTokenValid('valorant_tracker_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $match = $repository->findOwnedById($user, $id);
        if (!$match) {
            throw $this->createNotFoundException('Match introuvable.');
        }

        $syncService->delete($match);
        $this->addFlash('success', 'Match supprimé avec succès.');

        return $this->redirectToRoute('valorant_tracker_dashboard');
    }

    /** @return array{riotName:string,riotTag:string,region:string} */
    private function getTrackerProfile(Request $request): array
    {
        $session = $request->getSession();

        return [
            'riotName' => (string) $session->get('valorant_tracker.riot_name', ''),
            'riotTag' => (string) $session->get('valorant_tracker.riot_tag', ''),
            'region' => (string) $session->get('valorant_tracker.region', 'eu'),
        ];
    }

    private function saveTrackerProfile(Request $request, string $riotName, string $riotTag, string $region): void
    {
        $session = $request->getSession();
        $session->set('valorant_tracker.riot_name', $riotName);
        $session->set('valorant_tracker.riot_tag', $riotTag);
        $session->set('valorant_tracker.region', $region);
    }

    private function getSecureUser(): User
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        return $user;
    }
}
