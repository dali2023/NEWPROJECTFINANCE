<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CompanySettings;
use App\Form\CompanySettingsFormType;
use App\Repository\CompanySettingsRepository;
use App\Service\AuditLogger;
use App\Service\DocumentStorage;
use App\Service\Settings\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Paramétrage de la société : identité, coordonnées et logo.
 */
#[Route('/admin/parametres/societe', name: 'app_admin_settings_company')]
#[IsGranted('admin.settings')]
final class CompanySettingsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        CompanySettingsRepository $repository,
        EntityManagerInterface $entityManager,
        DocumentStorage $documentStorage,
        SettingsProvider $settingsProvider,
        AuditLogger $auditLogger,
    ): Response {
        $existing = $repository->findSingleton();
        $company = $existing ?? new CompanySettings();
        $isNew = null === $existing;

        $before = $this->snapshot($company);
        $form = $this->createForm(CompanySettingsFormType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logo */
            $logo = $form->get('logo')->getData();
            $removeLogo = (bool) $form->get('removeLogo')->getData();
            $oldLogoPath = $company->getLogoPath();

            try {
                if ($logo instanceof UploadedFile) {
                    $logoMimeType = $logo->getMimeType();
                    $logoOriginalName = $logo->getClientOriginalName();
                    $path = $documentStorage->store($logo, 'company');
                    $company->setLogoPath($path)
                        ->setLogoMimeType($logoMimeType)
                        ->setLogoOriginalName($logoOriginalName);

                    if (null !== $oldLogoPath) {
                        $documentStorage->delete($oldLogoPath);
                    }
                } elseif ($removeLogo && null !== $oldLogoPath) {
                    $company->setLogoPath(null)
                        ->setLogoMimeType(null)
                        ->setLogoOriginalName(null);
                    $documentStorage->delete($oldLogoPath);
                }

                $entityManager->persist($company);
                $entityManager->flush();
                $settingsProvider->reset();

                $auditLogger->log(
                    $isNew ? 'create' : 'update',
                    'CompanySettings',
                    $company->getIdString(),
                    $isNew ? null : $before,
                    $this->snapshot($company) + ['logo' => $company->hasLogo()],
                    'success',
                    'M01',
                    'Mise à jour des paramètres société',
                );

                $this->addFlash('success', 'Les paramètres de la société ont été enregistrés.');

                return $this->redirectToRoute('app_admin_settings_company');
            } catch (\Throwable $exception) {
                $form->addError(new FormError('Impossible d’enregistrer les paramètres : '.$exception->getMessage()));
            }
        }

        return $this->render('admin/settings/company.html.twig', [
            'form' => $form,
            'company' => $company,
            'is_new' => $isNew,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(CompanySettings $company): array
    {
        return [
            'legalName' => $company->getLegalName(),
            'tradeName' => $company->getTradeName(),
            'taxId' => $company->getTaxId(),
            'email' => $company->getEmail(),
            'phone' => $company->getPhone(),
            'phoneSecondary' => $company->getPhoneSecondary(),
            'address' => $company->getAddress(),
            'postalCode' => $company->getPostalCode(),
            'city' => $company->getCity(),
            'country' => $company->getCountry(),
            'website' => $company->getWebsite(),
        ];
    }
}