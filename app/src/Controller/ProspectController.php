<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ProspectConversionData;
use App\Dto\ProspectImportResult;
use App\Entity\Prospect;
use App\Enum\ProspectStatus;
use App\Form\ProspectConversionType;
use App\Form\ProspectFormType;
use App\Form\ProspectImportMappingType;
use App\Form\ProspectImportUploadType;
use App\Repository\AuditLogRepository;
use App\Repository\ProspectRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\Export\Column;
use App\Service\Export\SpreadsheetExporter;
use App\Service\ProspectConversionService;
use App\Service\ProspectImportService;
use App\Service\ReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Prospects commerciaux — import Excel et conversion en client/projet/contrat.
 */
#[Route('/prospects', name: 'app_prospect_')]
#[IsGranted('prospect.view')]
final class ProspectController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ProspectRepository $prospectRepository,
        UserRepository $userRepository,
    ): Response {
        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'status' => ProspectStatus::tryFrom($request->query->getString('statut')),
            'owner' => $this->findByUuid($userRepository, $request->query->getString('responsable')),
            'source' => $request->query->getString('source') ?: null,
        ];

        return $this->render('prospect/index.html.twig', [
            'result' => $prospectRepository->search($filters, $request->query->getInt('page', 1)),
            'filters' => $filters,
            'statuses' => ProspectStatus::cases(),
            'owners' => $userRepository->findActiveOrdered(),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(
        Request $request,
        ProspectRepository $prospectRepository,
        UserRepository $userRepository,
        SpreadsheetExporter $exporter,
    ): Response {
        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'status' => ProspectStatus::tryFrom($request->query->getString('statut')),
            'owner' => $this->findByUuid($userRepository, $request->query->getString('responsable')),
            'source' => $request->query->getString('source') ?: null,
        ];
        $result = $prospectRepository->search($filters, 1, 10000);

        $rows = [];
        foreach ($result['items'] as $prospect) {
            $rows[] = [
                $prospect->getReference(),
                $prospect->getName(),
                $prospect->getContactName(),
                $prospect->getEmail(),
                $prospect->getPhone(),
                $prospect->getCity(),
                $prospect->getSource(),
                $prospect->getStatus()->label(),
                $prospect->getOwner()?->getFullName(),
                $prospect->getEstimatedAmountHt(),
                $prospect->getProbability(),
                $prospect->getExpectedCloseDate(),
                $prospect->getNextAction(),
                $prospect->getNextActionDate(),
                $prospect->getConvertedClient()?->getCode(),
                $prospect->getConvertedProject()?->getCode(),
                $prospect->getConvertedContract()?->getReference(),
            ];
        }

        $context = [];
        if (null !== $filters['term']) {
            $context[] = 'Recherche : '.$filters['term'];
        }
        if (null !== $filters['status']) {
            $context[] = 'Statut : '.$filters['status']->label();
        }
        if (null !== $filters['owner']) {
            $context[] = 'Responsable : '.$filters['owner']->getFullName();
        }
        if (null !== $filters['source']) {
            $context[] = 'Source : '.$filters['source'];
        }
        $context[] = $result['total'].' prospect(s)';

        return $exporter->download('prospects', 'Prospects commerciaux', [
            Column::text('Référence', 16),
            Column::text('Raison sociale', 32),
            Column::text('Contact', 22),
            Column::text('E-mail', 28),
            Column::text('Téléphone', 16),
            Column::text('Ville', 16),
            Column::text('Source', 18),
            Column::text('Statut', 16),
            Column::text('Responsable', 20),
            Column::money('Montant estimé HT'),
            Column::integer('Probabilité (%)'),
            Column::date('Conclusion prévue'),
            Column::text('Prochaine action', 30),
            Column::date('Date action'),
            Column::text('Client', 16),
            Column::text('Projet', 16),
            Column::text('Contrat', 16),
        ], $rows, $context);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('prospect.manage')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceGenerator $referenceGenerator,
        AuditLogger $auditLogger,
    ): Response {
        $prospect = new Prospect();
        $prospect->setReference($referenceGenerator->next(ReferenceGenerator::MODULE_PROSPECT))
            ->setOwner($this->getUser())
            ->setCreatedBy($this->getUser());

        $form = $this->createForm(ProspectFormType::class, $prospect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (ProspectStatus::Converted === $prospect->getStatus()) {
                $form->addError(new FormError('Le statut « Converti » est réservé à la conversion automatique.'));
            } else {
                $entityManager->persist($prospect);
                $entityManager->flush();

                $auditLogger->log('create', 'Prospect', $prospect->getIdString(), null, $this->snapshot($prospect), 'success', 'M03', 'Création d’un prospect');

                $this->addFlash('success', sprintf('Le prospect %s a été créé.', $prospect->getReference()));

                return $this->redirectToRoute('app_prospect_show', ['id' => $prospect->getIdString()]);
            }
        }

        return $this->render('prospect/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function show(Prospect $prospect, AuditLogRepository $auditLogRepository): Response
    {
        return $this->render('prospect/show.html.twig', [
            'prospect' => $prospect,
            'history' => $auditLogRepository->findForEntity('Prospect', $prospect->getIdString(), 20),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('prospect.manage')]
    public function edit(
        Prospect $prospect,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        if ($prospect->isConverted()) {
            $this->addFlash('info', 'Ce prospect a été converti : modifiez désormais le client, le projet ou le contrat.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $prospect->getIdString()]);
        }

        $before = $this->snapshot($prospect);
        $previousStatus = $prospect->getStatus();
        $form = $this->createForm(ProspectFormType::class, $prospect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (ProspectStatus::Converted === $prospect->getStatus()) {
                $form->addError(new FormError('Le statut « Converti » est réservé à la conversion automatique.'));
            } else {
                $entityManager->flush();

                $statusChanged = $previousStatus !== $prospect->getStatus();
                $auditLogger->log(
                    $statusChanged ? 'status_change' : 'update',
                    'Prospect',
                    $prospect->getIdString(),
                    $before,
                    $this->snapshot($prospect),
                    'success',
                    'M03',
                    $statusChanged
                        ? sprintf('Statut : %s → %s', $previousStatus->label(), $prospect->getStatus()->label())
                        : 'Modification d’un prospect',
                );

                $this->addFlash('success', 'Le prospect a été mis à jour.');

                return $this->redirectToRoute('app_prospect_show', ['id' => $prospect->getIdString()]);
            }
        }

        return $this->render('prospect/edit.html.twig', [
            'form' => $form,
            'prospect' => $prospect,
        ]);
    }

    #[Route('/{id}/convertir', name: 'convert', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('prospect.convert')]
    public function convert(
        Prospect $prospect,
        Request $request,
        ProspectConversionService $conversionService,
    ): Response {
        $this->denyAccessUnlessGranted('client.manage');
        $this->denyAccessUnlessGranted('project.manage');
        $this->denyAccessUnlessGranted('contract.manage');

        if ($prospect->isConverted()) {
            $this->addFlash('info', 'Ce prospect a déjà été converti.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $prospect->getIdString()]);
        }
        if (!$prospect->canConvert()) {
            $this->addFlash('error', 'Un prospect perdu ne peut pas être converti. Modifiez d’abord son statut.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $prospect->getIdString()]);
        }

        $data = new ProspectConversionData();
        $data->setProjectType(null)
            ->setProjectManager($this->getUser())
            ->setProjectName($prospect->getName())
            ->setContractTitle('Contrat — '.$prospect->getName())
            ->setContractAmountHt($prospect->getEstimatedAmountHt())
            ->setVatRate('19.0000')
            ->setCurrencyCode((string) $this->getParameter('app.currency'));

        $form = $this->createForm(ProspectConversionType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $result = $conversionService->convert($prospect, $data, $this->getUser());

                $this->addFlash('success', sprintf(
                    'Prospect converti : client %s, projet %s et contrat brouillon %s créés.',
                    $result->client->getCode(),
                    $result->project->getCode(),
                    $result->contract->getReference(),
                ));

                return $this->redirectToRoute('app_contract_show', ['id' => $result->contract->getIdString()]);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('prospect/convert.html.twig', [
            'prospect' => $prospect,
            'form' => $form,
        ]);
    }

    #[Route('/importer', name: 'import_upload', methods: ['GET', 'POST'])]
    #[IsGranted('prospect.import')]
    public function importUpload(Request $request, ProspectImportService $importService): Response
    {
        $form = $this->createForm(ProspectImportUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            if (null !== $file) {
                try {
                    $token = $importService->createPendingImport($file);

                    return $this->redirectToRoute('app_prospect_import_map', ['token' => $token]);
                } catch (\InvalidArgumentException|\RuntimeException $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('prospect/import_upload.html.twig', ['form' => $form]);
    }

    #[Route('/importer/{token}', name: 'import_map', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{32}'])]
    #[IsGranted('prospect.import')]
    public function importMap(
        string $token,
        Request $request,
        ProspectImportService $importService,
    ): Response {
        $pending = $importService->pendingImport($token);
        if (null === $pending) {
            $this->addFlash('error', 'Cet import est introuvable ou a expiré. Déposez à nouveau le fichier.');

            return $this->redirectToRoute('app_prospect_import_upload');
        }

        try {
            $preview = $importService->inspect($token);
        } catch (\InvalidArgumentException $exception) {
            $importService->discard($token);
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_prospect_import_upload');
        }

        $mapping = $importService->guessMapping($preview->columns);
        $form = $this->createForm(ProspectImportMappingType::class, $mapping, ['columns' => $preview->columns]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, int|null> $mappingData */
            $mappingData = $form->getData();
            if (null === $mappingData['name']) {
                $form->get('name')->addError(new FormError('Sélectionnez la colonne contenant la raison sociale.'));
            } else {
                try {
                    $result = $importService->import($token, $mappingData, $this->getUser());
                    $request->getSession()->set('prospect_import_result_'.$token, $result);
                    $importService->discard($token);

                    return $this->redirectToRoute('app_prospect_import_result', ['token' => $token]);
                } catch (\InvalidArgumentException|\RuntimeException $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('prospect/import_map.html.twig', [
            'form' => $form,
            'preview' => $preview,
            'token' => $token,
        ]);
    }

    #[Route('/importer/{token}/resultat', name: 'import_result', methods: ['GET'], requirements: ['token' => '[a-f0-9]{32}'])]
    #[IsGranted('prospect.import')]
    public function importResult(string $token, Request $request): Response
    {
        $key = 'prospect_import_result_'.$token;
        $result = $request->getSession()->get($key);
        if (!$result instanceof ProspectImportResult) {
            $this->addFlash('error', 'Aucun résultat d’import à afficher.');

            return $this->redirectToRoute('app_prospect_import_upload');
        }
        $request->getSession()->remove($key);

        return $this->render('prospect/import_result.html.twig', [
            'result' => $result,
        ]);
    }

    #[Route('/importer/{token}/annuler', name: 'import_cancel', methods: ['POST'], requirements: ['token' => '[a-f0-9]{32}'])]
    #[IsGranted('prospect.import')]
    public function importCancel(string $token, Request $request, ProspectImportService $importService): Response
    {
        if (!$this->isCsrfTokenValid('prospect_import_cancel_'.$token, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $importService->discard($token);
        $this->addFlash('info', 'L’import a été annulé et le fichier temporaire supprimé.');

        return $this->redirectToRoute('app_prospect_import_upload');
    }

    private function findByUuid(object $repository, string $value): ?object
    {
        if ('' === $value || !Uuid::isValid($value)) {
            return null;
        }

        return $repository->find($value);
    }

    /** @return array<string, mixed> */
    private function snapshot(Prospect $prospect): array
    {
        return [
            'reference' => $prospect->getReference(),
            'name' => $prospect->getName(),
            'email' => $prospect->getEmail(),
            'city' => $prospect->getCity(),
            'status' => $prospect->getStatus()->value,
            'owner' => $prospect->getOwner()?->getFullName(),
            'estimatedAmountHt' => $prospect->getEstimatedAmountHt(),
        ];
    }
}
