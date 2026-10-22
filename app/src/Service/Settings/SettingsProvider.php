<?php

declare(strict_types=1);

namespace App\Service\Settings;

use App\Entity\CompanySettings;
use App\Entity\MailSettings;
use App\Repository\CompanySettingsRepository;
use App\Repository\MailSettingsRepository;

/**
 * Accès unique aux paramètres de l'application.
 *
 * Les fiches sont chargées au plus une fois par requête. Si aucune n'existe
 * encore, une instance non persistée est retournée avec des valeurs par défaut
 * afin que les gabarits restent utilisables avant le premier paramétrage.
 */
final class SettingsProvider
{
    private ?CompanySettings $company = null;
    private bool $companyLoaded = false;

    private ?MailSettings $mail = null;
    private bool $mailLoaded = false;

    public function __construct(
        private readonly CompanySettingsRepository $companyRepository,
        private readonly MailSettingsRepository $mailRepository,
    ) {
    }

    public function company(): CompanySettings
    {
        if (!$this->companyLoaded) {
            $this->company = $this->companyRepository->findSingleton() ?? new CompanySettings();
            $this->companyLoaded = true;
        }

        return $this->company;
    }

    public function mail(): MailSettings
    {
        if (!$this->mailLoaded) {
            $this->mail = $this->mailRepository->findSingleton() ?? new MailSettings();
            $this->mailLoaded = true;
        }

        return $this->mail;
    }

    /** À appeler après une écriture pour éviter de réutiliser une fiche obsolète. */
    public function reset(): void
    {
        $this->company = null;
        $this->companyLoaded = false;
        $this->mail = null;
        $this->mailLoaded = false;
    }
}