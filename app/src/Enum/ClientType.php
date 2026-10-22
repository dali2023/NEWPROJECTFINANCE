<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Nature du client (§4.2).
 */
enum ClientType: string implements LabeledEnum
{
    case Public = 'public';
    case Private = 'private';
    case Association = 'association';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Organisme public',
            self::Private => 'Entreprise privée',
            self::Association => 'Association',
            self::Other => 'Autre',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Public => 'badge--info',
            self::Private => 'badge--neutral',
            self::Association => 'badge--warning',
            self::Other => 'badge--muted',
        };
    }

    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }
}
