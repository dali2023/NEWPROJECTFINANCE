<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Journalise les tentatives d'accès refusées (§3.6, Test F).
 *
 * Un refus de permission ou une tentative d'accès à un projet non autorisé
 * laisse une trace dans audit_logs avec l'utilisateur, la ressource visée et le
 * résultat « denied ».
 */
#[AsEventListener(event: ExceptionEvent::class, priority: -10)]
final class AccessDeniedListener
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AccessDeniedHttpException && !$exception instanceof AccessDeniedException) {
            return;
        }

        try {
            $request = $event->getRequest();
            $this->auditLogger->denied(
                'deny',
                'Route',
                null,
                sprintf(
                    'Accès refusé à %s %s',
                    $request->getMethod(),
                    $request->getPathInfo(),
                ),
            );
        } catch (\Throwable) {
            // La journalisation ne doit jamais masquer l'erreur d'origine.
        }
    }
}
