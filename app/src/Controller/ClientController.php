<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Enum\ClientType;
use App\Form\ClientFormType;
use App\Repository\ClientRepository;
use App\Service\AuditLogger;
use App\Service\Export\Column;
use App\Service\Export\SpreadsheetExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * M02 — Clients (§4.2, §7.2).
 */
#[Route('/clients', name: 'app_client_')]
#[IsGranted('client.view')]
final class ClientController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ClientRepository $clientRepository): Response
    {
        $term = $request->query->getString('q') ?: null;
        $typeValue = $request->query->getString('type');
        $type = '' !== $typeValue ? ClientType::tryFrom($typeValue) : null;
        $activeOnly = $request->query->has('actifs') ? true : null;

        $result = $clientRepository->search(
            $term,
            $type,
            $activeOnly,
            $request->query->getInt('page', 1),
        );

        return $this->render('client/index.html.twig', [
            'result' => $result,
            'term' => $term,
            'type' => $type,
            'active_only' => $activeOnly,
            'client_types' => ClientType::cases(),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request, ClientRepository $clientRepository, SpreadsheetExporter $exporter): Response
    {
        $term = $request->query->getString('q') ?: null;
        $typeValue = $request->query->getString('type');
        $type = '' !== $typeValue ? ClientType::tryFrom($typeValue) : null;
        $activeOnly = $request->query->has('actifs') ? true : null;

        $result = $clientRepository->search($term, $type, $activeOnly, 1, 10000);

        $rows = [];
        foreach ($result['items'] as $client) {
            $rows[] = [
                $client->getCode(),
                $client->getName(),
                $client->getClientType()->label(),
                $client->getContactName(),
                $client->getEmail(),
                $client->getPhone(),
                $client->getCity(),
                $client->getPaymentTermsDays(),
                $client->isActive() ? 'Actif' : 'Inactif',
            ];
        }

        $context = [];
        if (null !== $term) {
            $context[] = 'Recherche : '.$term;
        }
        if (null !== $type) {
            $context[] = 'Type : '.$type->label();
        }
        if ($activeOnly) {
            $context[] = 'Clients actifs seulement';
        }
        $context[] = $result['total'].' client(s)';

        return $exporter->download('clients', 'Liste des clients', [
            Column::text('Code', 14),
            Column::text('Raison sociale', 32),
            Column::text('Type', 20),
            Column::text('Contact', 24),
            Column::text('E-mail', 28),
            Column::text('Téléphone', 16),
            Column::text('Ville', 16),
            Column::integer('Délai (jours)'),
            Column::text('Statut', 10),
        ], $rows, $context);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('client.manage')]
    public function new(Request $request, EntityManagerInterface $entityManager, ClientRepository $clientRepository, AuditLogger $auditLogger): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientFormType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($clientRepository->codeExists($client->getCode())) {
                $this->addFlash('error', 'Ce code client est déjà utilisé.');

                return $this->render('client/new.html.twig', ['form' => $form]);
            }

            $client->setCreatedBy($this->getUser());
            $entityManager->persist($client);
            $entityManager->flush();

            $auditLogger->log('create', 'Client', $client->getIdString(), null, $this->snapshot($client), 'success', 'M02', 'Création d’un client');

            $this->addFlash('success', sprintf('Le client %s a été enregistré.', $client->getName()));

            return $this->redirectToRoute('app_client_show', ['id' => $client->getIdString()]);
        }

        return $this->render('client/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Client $client): Response
    {
        return $this->render('client/show.html.twig', ['client' => $client]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('client.manage')]
    public function edit(
        Client $client,
        Request $request,
        EntityManagerInterface $entityManager,
        ClientRepository $clientRepository,
        AuditLogger $auditLogger,
    ): Response {
        $before = $this->snapshot($client);
        $form = $this->createForm(ClientFormType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($clientRepository->codeExists($client->getCode(), $client)) {
                $this->addFlash('error', 'Ce code client est déjà utilisé.');

                return $this->render('client/edit.html.twig', ['form' => $form, 'client' => $client]);
            }

            $entityManager->flush();
            $auditLogger->log('update', 'Client', $client->getIdString(), $before, $this->snapshot($client), 'success', 'M02', 'Modification d’un client');

            $this->addFlash('success', 'Les informations du client ont été mises à jour.');

            return $this->redirectToRoute('app_client_show', ['id' => $client->getIdString()]);
        }

        return $this->render('client/edit.html.twig', ['form' => $form, 'client' => $client]);
    }

    /**
     * Un client déjà utilisé ne peut pas être supprimé : il est désactivé
     * (§4.2, §5.1).
     */
    #[Route('/{id}/statut', name: 'toggle', methods: ['POST'])]
    #[IsGranted('client.manage')]
    public function toggle(Client $client, Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('client_toggle_'.$client->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $client->setIsActive(!$client->isActive());
        $entityManager->flush();

        $auditLogger->log(
            'update',
            'Client',
            $client->getIdString(),
            ['isActive' => !$client->isActive()],
            ['isActive' => $client->isActive()],
            'success',
            'M02',
            $client->isActive() ? 'Réactivation du client' : 'Désactivation du client',
        );

        $this->addFlash('success', $client->isActive() ? 'Le client a été réactivé.' : 'Le client a été désactivé.');

        return $this->redirectToRoute('app_client_index');
    }

    /** @return array<string, mixed> */
    private function snapshot(Client $client): array
    {
        return [
            'code' => $client->getCode(),
            'name' => $client->getName(),
            'type' => $client->getClientType()->value,
            'email' => $client->getEmail(),
            'isActive' => $client->isActive(),
        ];
    }
}
