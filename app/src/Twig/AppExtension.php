<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Money;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Fonctions de présentation communes aux gabarits :
 * - formatage monétaire exact (aucun flottant) ;
 * - vérification de permission côté gabarit (confort d'affichage : le contrôle
 *   d'accès réel reste effectué par les contrôleurs et les voters).
 */
final class AppExtension extends AbstractExtension
{
    public function __construct(private readonly Security $security)
    {
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
        ];
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
