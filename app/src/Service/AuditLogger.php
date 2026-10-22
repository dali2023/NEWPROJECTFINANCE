<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\FiscalYear;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Journalisation des opérations sensibles (§5.2 Z, §9.2, REC-018, Test F).
 *
 * Les lignes sont écrites dans la même transaction que l'opération métier :
 * une opération validée est toujours traçable, une opération annulée ne laisse
 * pas de trace trompeuse.
 */
final class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function log(
        string $action,
        string $entity,
        ?string $entityId = null,
        ?array $before = null,
        ?array $after = null,
        string $result = 'success',
        ?string $module = null,
        ?string $message = null,
        ?FiscalYear $fiscalYear = null,
        bool $flush = true,
    ): AuditLog {
        $log = new AuditLog($action, $entity);
        $log->setEntityId($entityId)
            ->setBeforeData($before)
            ->setAfterData($after)
            ->setResult($result)
            ->setModule($module)
            ->setMessage($message);

        $user = $this->currentUser();
        $log->setUser($user);

        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $log->setIpAddress($request->getClientIp());
            $log->setUserAgent(mb_substr((string) $request->headers->get('User-Agent'), 0, 255));
        }

        if (null !== $fiscalYear) {
            $log->setModule($log->getModule() ?? $fiscalYear->getLabel());
        }

        $this->entityManager->persist($log);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $log;
    }

    public function denied(string $action, string $entity, ?string $entityId = null, ?string $message = null): AuditLog
    {
        return $this->log($action, $entity, $entityId, null, null, 'denied', null, $message);
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
