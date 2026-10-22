<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DocumentStorage;
use App\Service\Settings\SettingsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Restitution publique du logo de la société.
 *
 * Le fichier reste hors du répertoire public ; seule cette route le sert, avec
 * un cache court. Aucune donnée sensible n'est exposée.
 */
final class CompanyLogoController extends AbstractController
{
    #[Route('/company/logo', name: 'app_company_logo', methods: ['GET'])]
    public function __invoke(SettingsProvider $settingsProvider, DocumentStorage $documentStorage): Response
    {
        $company = $settingsProvider->company();
        $path = $company->getLogoPath();

        if (null === $path || !$documentStorage->exists($path)) {
            throw $this->createNotFoundException('Aucun logo de société enregistré.');
        }

        $response = new BinaryFileResponse($documentStorage->file($path));
        $response->headers->set('Content-Type', $company->getLogoMimeType() ?: 'image/png');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $company->getLogoOriginalName() ?: 'logo');
        $response->setPublic();
        $response->setMaxAge(3600);
        $response->setAutoEtag();

        return $response;
    }
}