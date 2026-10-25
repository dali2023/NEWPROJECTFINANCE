<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Client;
use App\Enum\ClientType;
use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * REC-037 — exports Excel et impressions disponibles sur les écrans.
 */
final class ExportPrintTest extends DatabaseTestCase
{
    public function testClientListExposesExportAndPrintActions(): void
    {
        $user = $this->createUser('ROLE_EXPORT_UI', ['client.view']);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/clients');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-print]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/clients/export"]')->count());
    }

    public function testClientExportReturnsAValidExcelFile(): void
    {
        $user = $this->createUser('ROLE_EXPORT_XLSX', ['client.view']);
        $this->client->loginUser($user);

        $client = $this->createClientFixture('Client exportable');

        $this->client->request('GET', '/clients/export?q='.urlencode($client->getName()));
        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        self::assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));

        $binary = (string) $response->getContent();
        self::assertStringStartsWith('PK', $binary);

        $path = tempnam(sys_get_temp_dir(), 'one-erp-http-xlsx-');
        self::assertNotFalse($path);
        file_put_contents($path, $binary);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            $sharedStrings = (string) $zip->getFromName('xl/sharedStrings.xml');
            $zip->close();

            self::assertStringContainsString('Liste des clients', $sharedStrings);
            self::assertStringContainsString($client->getName(), $sharedStrings);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param list<string> $permissions
     */
    #[DataProvider('exportRoutes')]
    public function testEveryExportRouteIsReachable(string $route, array $permissions): void
    {
        $user = $this->createUser('ROLE_EXPORT_'.strtoupper(substr(md5($route), 0, 10)), $permissions);
        $this->client->loginUser($user);

        $this->client->request('GET', $route);

        self::assertResponseIsSuccessful();
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $this->client->getResponse()->headers->get('Content-Type'),
            sprintf('La route %s doit produire un classeur Excel.', $route),
        );
    }

    public function testExportIncludesMoreThanTheListPaginationCap(): void
    {
        $user = $this->createUser('ROLE_EXPORT_BULK', ['client.view']);
        $this->client->loginUser($user);

        $suffix = substr(uniqid('', true), -6);
        for ($i = 1; $i <= 205; ++$i) {
            $client = new Client();
            $client->setCode(sprintf('CLI-%s-%03d', $suffix, $i))
                ->setName(sprintf('Client export %03d', $i))
                ->setClientType(ClientType::Private)
                ->setPaymentTermsDays(30)
                ->setIsActive(true);
            $this->entityManager->persist($client);
        }
        $this->entityManager->flush();

        $this->client->request('GET', '/clients/export');
        self::assertResponseIsSuccessful();

        $binary = (string) $this->client->getResponse()->getContent();
        $path = tempnam(sys_get_temp_dir(), 'one-erp-bulk-xlsx-');
        self::assertNotFalse($path);
        file_put_contents($path, $binary);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
        } finally {
            @unlink($path);
        }

        // 205 lignes de données + titre + contexte + en-tête = 208 lignes.
        self::assertGreaterThanOrEqual(208, substr_count($sheet, '<row '));
    }

    #[DataProvider('listPages')]
    public function testListPagesExposePrintAndExportActions(string $route, bool $hasExport): void
    {
        $user = $this->createUser('ROLE_LIST_'.strtoupper(substr(md5($route), 0, 8)), [
            'client.view',
            'supplier.view',
            'prospect.view',
            'project.view_all',
            'contract.view_all',
            'invoice.view',
            'expense.view',
            'treasury.view',
            'partner.view',
            'admin.users',
            'admin.referentials',
            'admin.audit',
            'fiscal_year.close',
        ]);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', $route);

        self::assertResponseIsSuccessful(sprintf('La page %s doit rester accessible.', $route));
        self::assertGreaterThan(0, $crawler->filter('[data-print]')->count(), sprintf('Bouton Imprimer absent sur %s.', $route));

        if ($hasExport) {
            self::assertGreaterThan(
                0,
                $crawler->filter('.page-head__actions a[href$="/export"]')->count(),
                sprintf('Bouton Exporter Excel absent sur %s.', $route),
            );
        }
    }

    /** @return iterable<string, array{0: string, 1: bool}> */
    public static function listPages(): iterable
    {
        yield 'clients' => ['/clients', true];
        yield 'fournisseurs' => ['/fournisseurs', true];
        yield 'prospects' => ['/prospects', true];
        yield 'projets' => ['/projets', true];
        yield 'contrats' => ['/contrats', true];
        yield 'facturation contrats' => ['/contrats/facturation', true];
        yield 'notifications contrats' => ['/contrats/notifications', true];
        yield 'calendrier' => ['/contrats/calendrier', false];
        yield 'factures' => ['/factures', true];
        yield 'charges' => ['/charges', true];
        yield 'tresorerie' => ['/tresorerie', true];
        yield 'associes' => ['/associes', true];
        yield 'utilisateurs' => ['/admin/utilisateurs', true];
        yield 'roles' => ['/admin/roles', true];
        yield 'exercices' => ['/admin/exercices', true];
        yield 'types de projet' => ['/admin/types-projet', true];
        yield 'audit' => ['/admin/audit', true];
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function exportRoutes(): iterable
    {
        yield 'clients' => ['/clients/export', ['client.view']];
        yield 'fournisseurs' => ['/fournisseurs/export', ['supplier.view']];
        yield 'prospects' => ['/prospects/export', ['prospect.view']];
        yield 'projets' => ['/projets/export', ['project.view_all']];
        yield 'contrats' => ['/contrats/export', ['contract.view_all']];
        yield 'facturation contrats' => ['/contrats/facturation/export', ['contract.view_all']];
        yield 'notifications contrats' => ['/contrats/notifications/export', ['contract.view_all']];
        yield 'factures' => ['/factures/export', ['invoice.view']];
        yield 'charges' => ['/charges/export', ['expense.view']];
        yield 'tresorerie' => ['/tresorerie/export', ['treasury.view']];
        yield 'associes' => ['/associes/export', ['partner.view']];
        yield 'utilisateurs' => ['/admin/utilisateurs/export', ['admin.users']];
        yield 'roles' => ['/admin/roles/export', ['admin.users']];
        yield 'exercices' => ['/admin/exercices/export', ['admin.referentials']];
        yield 'types de projet' => ['/admin/types-projet/export', ['admin.referentials']];
        yield 'journal d audit' => ['/admin/audit/export', ['admin.audit']];
    }

    private function streamContent(Response $response): string
    {
        return (string) $response->getContent();
    }
}
