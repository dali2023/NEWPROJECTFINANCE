<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Client;
use App\Entity\Contract;
use App\Entity\Project;

/**
 * Objets créés par la conversion d'un prospect.
 */
final readonly class ProspectConversionResult
{
    public function __construct(
        public Client $client,
        public Project $project,
        public Contract $contract,
    ) {
    }
}
