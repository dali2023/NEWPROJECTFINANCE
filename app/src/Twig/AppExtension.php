<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Money;
use App\Service\Settings\SettingsProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Fonctions de présentation communes aux gabarits :
 * - formatage monétaire exact (aucun flottant) ;
 * - vérification de permission côté gabarit (confort d'affichage : le contrôle
 *   d'accès réel reste effectué par les contrôleurs et les voters) ;
 * - versionnement des assets statiques pour éviter les caches navigateur.
 */
final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly Packages $packages,
        private readonly string $projectDir,
        private readonly SettingsProvider $settingsProvider,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->formatMoney(...)),
            new TwigFilter('money_rate', $this->formatRate(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('can', $this->can(...)),
            new TwigFunction('percent', $this->percent(...)),
            new TwigFunction('versioned_asset', $this->versionedAsset(...)),
            new TwigFunction('company_settings', $this->settingsProvider->company(...)),
        ];
    }

    /**
     * URL d'un asset statique suffixée par sa date de modification.
     *
     * Nginx sert les fichiers statiques avec un cache long : sans ce
     * versionnement, une modification de CSS/JS restait invisible tant que le
     * navigateur réutilisait sa copie en cache.
     */
    public function versionedAsset(string $path): string
    {
        $url = $this->packages->getUrl($path);
        $file = $this->projectDir.'/public/'.ltrim($path, '/');
        $version = is_file($file) ? (string) filemtime($file) : '1';

        return $url.(\str_contains($url, '?') ? '&' : '?').'v='.$version;
    }

    public function formatMoney(?string $amount, ?string $currency = null): string
    {
        return Money::format($amount, $currency ?? 'TND');
    }

    public function formatRate(?string $rate): string
    {
        return Money::formatRate($rate);
    }

    public function can(string $permission): bool
    {
        return $this->security->isGranted($permission);
    }

    /** Pourcentage déjà calculé ; null → « n. a. » (§6.6, §6.11). */
    public function percent(?string $rate): string
    {
        if (null === $rate) {
            return 'n. a.';
        }

        return str_replace('.', ',', $rate).' %';
    }
}
