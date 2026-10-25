<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\ProjectType;
use App\Entity\Prospect;
use App\Enum\ContractStatus;
use App\Enum\ProjectStatus;
use App\Enum\ProspectStatus;
use App\Tests\Support\DatabaseTestCase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Module Prospects : import Excel, mise à jour des doublons, conversion et
 * contrôle des permissions.
 */
final class ProspectManagementTest extends DatabaseTestCase
{
    public function testConversionCreatesClientProjectAndDraftContract(): void
    {
        $user = $this->createUser('ROLE_ADMIN');
        $projectType = $this->createProjectType();
        $suffix = substr(uniqid('', true), -6);

        $prospect = new Prospect();
        $prospect->setReference('PRO-T-'.strtoupper($suffix))
            ->setName('Prospect conversion '.$suffix)
            ->setContactName('Contact '.$suffix)
            ->setEmail('conversion-'.$suffix.'@example.tn')
            ->setPhone('+216 71 000 000')
            ->setCity('Tunis')
            ->setCountry('Tunisie')
            ->setTaxIdentifier('MF-'.$suffix)
            ->setSource('Salon')
            ->setStatus(ProspectStatus::Qualified)
            ->setOwner($user)
            ->setCreatedBy($user)
            ->setEstimatedAmountHt('120000.000')
            ->setProbability(60);
        $this->entityManager->persist($prospect);
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/prospects/'.$prospect->getIdString().'/convertir');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Convertir le prospect')->form([
            'prospect_conversion[clientType]' => 'private',
            'prospect_conversion[paymentTermsDays]' => '30',
            'prospect_conversion[projectType]' => $projectType->getIdString(),
            'prospect_conversion[projectManager]' => $user->getIdString(),
            'prospect_conversion[projectName]' => 'Projet '.$suffix,
            'prospect_conversion[contractTitle]' => 'Contrat '.$suffix,
            'prospect_conversion[contractType]' => 'private',
            'prospect_conversion[contractAmountHt]' => '120000,000',
            'prospect_conversion[vatRate]' => '19',
            'prospect_conversion[currencyCode]' => 'TND',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $this->entityManager->clear();
        $converted = $this->entityManager->getRepository(Prospect::class)->find($prospect->getId());
        self::assertInstanceOf(Prospect::class, $converted);
        self::assertTrue($converted->isConverted());
        self::assertSame(ProspectStatus::Converted, $converted->getStatus());
        self::assertNotNull($converted->getConvertedAt());
        self::assertNotNull($converted->getConvertedBy());

        $client = $converted->getConvertedClient();
        $project = $converted->getConvertedProject();
        $contract = $converted->getConvertedContract();
        self::assertNotNull($client);
        self::assertNotNull($project);
        self::assertNotNull($contract);

        self::assertStringStartsWith('CLI-', $client->getCode());
        self::assertSame('Prospect conversion '.$suffix, $client->getName());
        self::assertSame('Tunis', $client->getCity());
        self::assertSame('MF-'.$suffix, $client->getTaxIdentifier());
        self::assertSame(30, $client->getPaymentTermsDays());

        self::assertStringStartsWith('PRJ-', $project->getCode());
        self::assertSame('Projet '.$suffix, $project->getName());
        self::assertTrue($project->getClient()->getId()->equals($client->getId()));
        self::assertSame(ProjectStatus::Opportunity, $project->getStatus());
        self::assertSame(60, $project->getProbability());
        self::assertSame('120000.000', $project->getContractAmountHt());
        self::assertSame($user->getIdString(), $project->getManager()?->getIdString());

        self::assertStringStartsWith('CTR-', $contract->getReference());
        self::assertSame('Contrat '.$suffix, $contract->getTitle());
        self::assertSame(ContractStatus::Draft, $contract->getStatus());
        self::assertSame('120000.000', $contract->getAmountHt());
        self::assertSame('TND', $contract->getCurrencyCode());
        self::assertTrue($contract->getClient()->getId()->equals($client->getId()));
        self::assertTrue($contract->getProject()->getId()->equals($project->getId()));

        $audit = $this->entityManager->getRepository(AuditLog::class)->findBy([
            'entity' => 'Prospect',
            'entityId' => $converted->getIdString(),
            'action' => 'convert',
        ]);
        self::assertCount(1, $audit);
    }

    public function testAdminCanCreateAndEditProspect(): void
    {
        $user = $this->createUser('ROLE_ADMIN');
        $suffix = substr(uniqid('', true), -6);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/prospects/nouveau');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer le prospect')->form([
            'prospect_form[name]' => 'Nouveau prospect '.$suffix,
            'prospect_form[contactName]' => 'Contact '.$suffix,
            'prospect_form[email]' => 'nouveau-'.$suffix.'@example.tn',
            'prospect_form[phone]' => '+216 70 000 000',
            'prospect_form[city]' => 'Sousse',
            'prospect_form[source]' => 'Recommandation',
            'prospect_form[status]' => 'qualified',
            'prospect_form[estimatedAmountHt]' => '1500,500',
            'prospect_form[probability]' => '70',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nouveau prospect '.$suffix);

        $this->entityManager->clear();
        $prospect = $this->entityManager->getRepository(Prospect::class)
            ->findOneBy(['name' => 'Nouveau prospect '.$suffix]);
        self::assertInstanceOf(Prospect::class, $prospect);
        self::assertStringStartsWith('PRO-', $prospect->getReference());
        self::assertSame(ProspectStatus::Qualified, $prospect->getStatus());
        self::assertSame('1500.500', $prospect->getEstimatedAmountHt());
        self::assertSame(70, $prospect->getProbability());
        self::assertSame($user->getIdString(), $prospect->getOwner()?->getIdString());

        $crawler = $this->client->request('GET', '/prospects/'.$prospect->getIdString().'/modifier');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer les modifications')->form([
            'prospect_form[name]' => 'Prospect modifié '.$suffix,
            'prospect_form[status]' => 'in_discussion',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Prospect modifié '.$suffix);

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(Prospect::class)->find($prospect->getId());
        self::assertInstanceOf(Prospect::class, $updated);
        self::assertSame('Prospect modifié '.$suffix, $updated->getName());
        self::assertSame(ProspectStatus::InDiscussion, $updated->getStatus());
    }

    public function testImportUpdatesDuplicatesByEmailThenNameAndCity(): void
    {
        $user = $this->createUser('ROLE_ADMIN');
        $suffix = substr(uniqid('', true), -6);
        $alphaEmail = 'alpha-'.$suffix.'@example.tn';
        $alphaName = 'Alpha '.$suffix;
        $betaName = 'Beta '.$suffix;

        $firstFile = $this->createSpreadsheet([
            ['Raison sociale', 'Email', 'Ville', 'Téléphone', 'Statut', 'Montant estimé HT'],
            [$alphaName, $alphaEmail, 'Tunis', '11111111', 'Qualifié', '1 250,500'],
            [$betaName, '', 'Sfax', '22222222', 'Nouveau', '500'],
            ['Gamma '.$suffix, 'adresse-invalide', 'Sousse', '33333333', 'Nouveau', '100'],
        ]);

        $this->client->loginUser($user);
        $this->importSpreadsheet($firstFile);

        self::assertSelectorTextContains('body', 'Adresse électronique invalide.');

        $this->entityManager->clear();
        $repository = $this->entityManager->getRepository(Prospect::class);
        $alpha = $repository->findOneBy(['email' => $alphaEmail]);
        $beta = $repository->findOneBy(['name' => $betaName, 'city' => 'Sfax']);
        self::assertInstanceOf(Prospect::class, $alpha);
        self::assertInstanceOf(Prospect::class, $beta);
        self::assertNull($beta->getEmail());
        self::assertSame('1250.500', $alpha->getEstimatedAmountHt());
        self::assertSame(ProspectStatus::Qualified, $alpha->getStatus());
        self::assertNull($repository->findOneBy(['name' => 'Gamma '.$suffix]));

        $betaId = $beta->getIdString();
        $alphaId = $alpha->getIdString();

        $secondFile = $this->createSpreadsheet([
            ['Raison sociale', 'Email', 'Ville', 'Téléphone', 'Statut'],
            ['Alpha renommé '.$suffix, $alphaEmail, 'Tunis', '99999999', 'En discussion'],
            [$betaName, 'beta-'.$suffix.'@example.tn', 'Sfax', '88888888', 'Gagné'],
        ]);

        $this->importSpreadsheet($secondFile);

        $this->entityManager->clear();
        $repository = $this->entityManager->getRepository(Prospect::class);
        $alpha = $repository->find($alphaId);
        $beta = $repository->find($betaId);
        self::assertInstanceOf(Prospect::class, $alpha);
        self::assertInstanceOf(Prospect::class, $beta);

        self::assertSame('Alpha renommé '.$suffix, $alpha->getName());
        self::assertSame('99999999', $alpha->getPhone());
        self::assertSame(ProspectStatus::InDiscussion, $alpha->getStatus());
        self::assertSame('beta-'.$suffix.'@example.tn', $beta->getEmail());
        self::assertSame('88888888', $beta->getPhone());
        self::assertSame(ProspectStatus::Won, $beta->getStatus());

        self::assertSame(1, $this->countProspectsByName('Alpha renommé '.$suffix));
        self::assertSame(1, $this->countProspectsByName($betaName));

        $imports = $this->entityManager->getRepository(AuditLog::class)->findBy(
            ['entity' => 'Prospect', 'action' => 'import'],
            ['occurredAt' => 'DESC'],
            2,
        );
        self::assertCount(2, $imports);

        @unlink($firstFile);
        @unlink($secondFile);
    }

    public function testImportRequiresExplicitColumnMapping(): void
    {
        $user = $this->createUser('ROLE_ADMIN');
        $suffix = substr(uniqid('', true), -6);
        $name = 'Mapping '.$suffix;
        $email = 'mapping-'.$suffix.'@example.tn';
        $file = $this->createSpreadsheet([
            ['Colonne A', 'Colonne B', 'Colonne C'],
            [$name, $email, 'Bizerte'],
        ]);

        $this->client->loginUser($user);
        $crawler = $this->uploadSpreadsheet($file);

        $form = $crawler->selectButton('Importer les lignes')->form([
            'prospect_import_mapping[name]' => '0',
            'prospect_import_mapping[email]' => '1',
            'prospect_import_mapping[city]' => '2',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Résultat de l’import');

        $this->entityManager->clear();
        $prospect = $this->entityManager->getRepository(Prospect::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(Prospect::class, $prospect);
        self::assertSame($name, $prospect->getName());
        self::assertSame('Bizerte', $prospect->getCity());

        @unlink($file);
    }

    public function testProspectRoutesEnforceServerSidePermissions(): void
    {
        $viewer = $this->createUser('ROLE_TEST_PROSPECT_VIEW', ['prospect.view']);

        $prospect = new Prospect();
        $prospect->setReference('PRO-P-'.strtoupper(substr(uniqid('', true), -6)))
            ->setName('Prospect permissions')
            ->setStatus(ProspectStatus::Qualified)
            ->setOwner($viewer)
            ->setCreatedBy($viewer);
        $this->entityManager->persist($prospect);
        $this->entityManager->flush();

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/prospects');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/prospects/importer');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/prospects/nouveau');
        self::assertResponseStatusCodeSame(403);

        $converter = $this->createUser('ROLE_TEST_PROSPECT_CONVERT', ['prospect.view', 'prospect.convert']);
        $this->client->loginUser($converter);
        $this->client->request('GET', '/prospects/'.$prospect->getIdString().'/convertir');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/prospects/'.$prospect->getIdString());
        self::assertResponseIsSuccessful();
    }

    private function createProjectType(): ProjectType
    {
        $suffix = substr(uniqid('', true), -6);
        $type = new ProjectType();
        $type->setCode('PT_'.strtoupper($suffix))
            ->setLabel('Type de test '.$suffix)
            ->setIsActive(true);
        $this->entityManager->persist($type);
        $this->entityManager->flush();

        return $type;
    }

    /**
     * @param list<list<string>> $rows
     */
    private function createSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = sys_get_temp_dir().'/prospects-'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function importSpreadsheet(string $path): void
    {
        $crawler = $this->uploadSpreadsheet($path);

        $form = $crawler->selectButton('Importer les lignes')->form();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Résultat de l’import');
    }

    private function uploadSpreadsheet(string $path): Crawler
    {
        $this->client->request('GET', '/prospects/importer');
        self::assertResponseIsSuccessful();

        $form = $this->client->getCrawler()->selectButton('Analyser le fichier')->form();
        $form['prospect_import_upload[file]']->upload($path);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Associer les colonnes');

        return $this->client->getCrawler();
    }

    private function countProspectsByName(string $name): int
    {
        return (int) $this->entityManager->getRepository(Prospect::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
