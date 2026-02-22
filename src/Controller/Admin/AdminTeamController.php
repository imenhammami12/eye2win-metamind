<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Repository\TeamMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/teams')]
#[IsGranted('ROLE_ADMIN')]
class AdminTeamController extends AbstractController
{
    #[Route('/', name: 'admin_teams_index')]
    public function index(
        Request $request,
        TeamRepository $teamRepository
    ): Response {
        $statusFilter = $request->query->get('status', '');
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort_by', 'createdAt');
        $sortOrder = $request->query->get('sort_order', 'DESC');
        
        // Validate sort parameters
        $validSortFields = ['createdAt', 'name', 'maxMembers'];
        $sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'createdAt';
        $sortOrder = in_array($sortOrder, ['ASC', 'DESC']) ? $sortOrder : 'DESC';
        
        $queryBuilder = $teamRepository->createQueryBuilder('t')
            ->leftJoin('t.owner', 'o')
            ->leftJoin('t.teamMemberships', 'tm')
            ->addSelect('o', 'tm');
        
        // Status filter
        if ($statusFilter === 'active') {
            $queryBuilder->andWhere('t.isActive = :active')
                ->setParameter('active', true);
        } elseif ($statusFilter === 'inactive') {
            $queryBuilder->andWhere('t.isActive = :active')
                ->setParameter('active', false);
        }
        
        // Search filter
        if ($search) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('t.name', ':search'),
                    $queryBuilder->expr()->like('t.description', ':search'),
                    $queryBuilder->expr()->like('o.username', ':search'),
                    $queryBuilder->expr()->like('o.email', ':search')
                )
            )->setParameter('search', '%' . $search . '%');
        }
        
        // Apply sorting
        $queryBuilder->orderBy('t.' . $sortBy, $sortOrder);
        
        $teams = $queryBuilder->getQuery()->getResult();
        
        $stats = $teamRepository->getStatistics();
        
        return $this->render('admin/teams/index.html.twig', [
            'teams' => $teams,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}', name: 'admin_teams_show', requirements: ['id' => '\d+'])]
    public function show(
        Team $team,
        TeamMembershipRepository $membershipRepository
    ): Response {
        $activeMembers = $membershipRepository->findActiveMembers($team);
        
        return $this->render('admin/teams/show.html.twig', [
            'team' => $team,
            'activeMembers' => $activeMembers,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'admin_teams_toggle_status', methods: ['POST'])]
    public function toggleStatus(
        Request $request,
        Team $team,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('toggle-status-' . $team->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $team->setIsActive(!$team->isActive());
        $em->flush();

        $this->addFlash('success', 'Le statut de l\'équipe a été mis à jour');
        return $this->redirectToRoute('admin_teams_show', ['id' => $team->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_teams_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Team $team,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('delete-' . $team->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        // Delete team logo if exists
        if ($team->getLogo()) {
            $logoPath = $this->getParameter('teams_directory') . '/' . $team->getLogo();
            if (file_exists($logoPath)) {
                unlink($logoPath);
            }
        }

        $em->remove($team);
        $em->flush();

        $this->addFlash('success', 'Équipe supprimée avec succès');
        return $this->redirectToRoute('admin_teams_index');
    }
}
