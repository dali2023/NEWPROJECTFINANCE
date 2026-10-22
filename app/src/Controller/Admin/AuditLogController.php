<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * M13 — Journal d'audit (§5.2 Z, §7.12, REC-018).
 */
#[Route('/admin/audit', name: 'app_admin_audit_')]
#[IsGranted('admin.audit')]
final class AuditLogController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, AuditLogRepository $auditLogRepository): Response
    {
        $from = $request->query->getString('du');
        $to = $request->query->getString('au');

        return $this->render('admin/audit/index.html.twig', [
            'result' => $auditLogRepository->search(
                entity: $request->query->getString('entite') ?: null,
                action: $request->query->getString('action') ?: null,
                username: $request->query->getString('utilisateur') ?: null,
                from: '' !== $from ? new \DateTimeImmutable($from.' 00:00:00') : null,
                to: '' !== $to ? new \DateTimeImmutable($to.' 23:59:59') : null,
                page: $request->query->getInt('page', 1),
                perPage: 50,
            ),
            'entities' => $this->distinctEntities($auditLogRepository),
            'filters' => [
                'entite' => $request->query->getString('entite'),
                'action' => $request->query->getString('action'),
                'utilisateur' => $request->query->getString('utilisateur'),
                'du' => $from,
                'au' => $to,
            ],
        ]);
    }

    /** @return list<string> */
    private function distinctEntities(AuditLogRepository $auditLogRepository): array
    {
        $rows = $auditLogRepository->createQueryBuilder('a')
            ->select('DISTINCT a.entity')
            ->orderBy('a.entity', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => (string) $row['entity'], $rows);
    }
}
