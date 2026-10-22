<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Service\Money;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Convertit une saisie utilisateur (« 1 250,5 ») en montant décimal normalisé
 * (« 1250.500 »). Aucun flottant n'intervient dans la conversion.
 *
 * @implements DataTransformerInterface<string|null, string|null>
 */
final class DecimalTransformer implements DataTransformerInterface
{
    public function __construct(private readonly int $scale = Money::SCALE)
    {
    }

    public function transform(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return Money::round((string) $value, $this->scale);
    }

    public function reverseTransform(mixed $value): ?string
    {
        if (null === $value || '' === trim((string) $value)) {
            return null;
        }

        try {
            return Money::normalize((string) $value, $this->scale);
        } catch (\InvalidArgumentException $exception) {
            throw new TransformationFailedException($exception->getMessage());
        }
    }
}
