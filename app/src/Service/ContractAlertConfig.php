<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ContractNotificationType;

/**
 * Paramétrage des délais d'alerte (§15.7).
 *
 * Les valeurs par défaut sont définies dans config/packages/contract_alerts.yaml
 * et peuvent être ajustées sans modifier le code métier. Elles ne remplacent ni
 * les délais légaux ni les délais contractuels.
 */
final class ContractAlertConfig
{
    /**
     * @param array<string, list<int|string>> $delays
     */
    public function __construct(private readonly array $delays = [])
    {
    }

    /** @return list<int> */
    public function delaysFor(ContractNotificationType $type): array
    {
        $configured = $this->delays[$type->value] ?? null;
        if (!\is_array($configured) || [] === $configured) {
            return $type->defaultDelays();
        }

        return array_values(array_map(static fn (int|string $delay): int => (int) $delay, $configured));
    }

    /**
     * @return array<string, list<int>>
     */
    public function all(): array
    {
        $all = [];
        foreach (ContractNotificationType::cases() as $type) {
            $all[$type->value] = $this->delaysFor($type);
        }

        return $all;
    }
}
