<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Mode de chiffrement de la connexion SMTP.
 */
enum MailEncryption: string implements LabeledEnum
{
    case None = 'none';
    case StartTls = 'starttls';
    case Tls = 'tls';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucun (réseau interne)',
            self::StartTls => 'STARTTLS (port 587)',
            self::Tls => 'TLS implicite (port 465)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::None => 'badge--muted',
            self::StartTls => 'badge--success',
            self::Tls => 'badge--success',
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

    public function isSecure(): bool
    {
        return self::None !== $this;
    }
}
