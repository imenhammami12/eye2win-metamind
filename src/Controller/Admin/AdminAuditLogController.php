<?php

namespace App\Controller\Admin;

use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit-logs')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class AdminAuditLogController extends AbstractController
{
    #[Route('/', name: 'admin_audit_logs_index')]
    public function index(
        Request $request,
        AuditLogRepository $auditLogRepository
    ): Response {
        // Filtres
        $action = $request->query->get('action', '');
        $entityType = $request->query->get('entity_type', '');
        $userId = $request->query->get('user_id', '');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo = $request->query->get('date_to', '');
        $sortBy = $request->query->get('sort_by', 'createdAt');
        $sortOrder = $request->query->get('sort_order', 'DESC');
        
        $queryBuilder = $auditLogRepository->createQueryBuilder('al')
            ->leftJoin('al.user', 'u')
            ->addSelect('u');
        
        // Filtre par action
        if ($action) {
            $queryBuilder->andWhere('al.action = :action')
                ->setParameter('action', $action);
        }
        
        // Filtre par type d'entité
        if ($entityType) {
            $queryBuilder->andWhere('al.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }
        
        // Filtre par utilisateur
        if ($userId) {
            $queryBuilder->andWhere('al.user = :userId')
                ->setParameter('userId', $userId);
        }
        
        // Filtre par date de début
        if ($dateFrom) {
            try {
                $dateFromObj = new \DateTime($dateFrom . ' 00:00:00');
                $queryBuilder->andWhere('al.createdAt >= :dateFrom')
                    ->setParameter('dateFrom', $dateFromObj);
            } catch (\Exception $e) {
                // Date invalide, on ignore
            }
        }
        
        // Filtre par date de fin
        if ($dateTo) {
            try {
                $dateToObj = new \DateTime($dateTo . ' 23:59:59');
                $queryBuilder->andWhere('al.createdAt <= :dateTo')
                    ->setParameter('dateTo', $dateToObj);
            } catch (\Exception $e) {
                // Date invalide, on ignore
            }
        }
        
        // Tri
        $validSortFields = ['createdAt', 'action', 'entityType'];
        if (in_array($sortBy, $validSortFields)) {
            $validSortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) 
                ? strtoupper($sortOrder) 
                : 'DESC';
            $queryBuilder->orderBy('al.' . $sortBy, $validSortOrder);
        } else {
            $queryBuilder->orderBy('al.createdAt', 'DESC');
        }
        
        $auditLogs = $queryBuilder->getQuery()->getResult();
        
        // Récupérer les actions uniques pour le filtre
        $actions = $auditLogRepository->createQueryBuilder('al')
            ->select('DISTINCT al.action')
            ->orderBy('al.action', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Récupérer les types d'entités uniques pour le filtre
        $entityTypes = $auditLogRepository->createQueryBuilder('al')
            ->select('DISTINCT al.entityType')
            ->orderBy('al.entityType', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Statistiques
        $stats = [
            'total' => $auditLogRepository->count([]),
            'today' => count($auditLogRepository->findByDate(new \DateTime('today'))),
            'last7Days' => count($auditLogRepository->findByDate(new \DateTime('-7 days'))),
            'last30Days' => count($auditLogRepository->findByDate(new \DateTime('-30 days'))),
        ];
        
        return $this->render('admin/audit_logs/index.html.twig', [
            'auditLogs' => $auditLogs,
            'actions' => array_column($actions, 'action'),
            'entityTypes' => array_column($entityTypes, 'entityType'),
            'action' => $action,
            'entityType' => $entityType,
            'userId' => $userId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'stats' => $stats,
        ]);
    }
    
    #[Route('/{id}', name: 'admin_audit_logs_show', requirements: ['id' => '\d+'])]
    public function show(int $id, AuditLogRepository $auditLogRepository): Response
    {
        $auditLog = $auditLogRepository->find($id);
        
        if (!$auditLog) {
            throw $this->createNotFoundException('Audit log not found');
        }
        
        return $this->render('admin/audit_logs/show.html.twig', [
            'auditLog' => $auditLog,
        ]);
    }
}