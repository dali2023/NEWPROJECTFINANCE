<?php

declare(strict_types=1);

namespace App\Service\Settings;

/**
 * Chiffrement symétrique des secrets stockés en base (mot de passe SMTP).
 *
 * AES-256-GCM garantit à la fois la confidentialité et l'intégrité. La clé est
 * dérivée du secret applicatif : une sauvegarde de la base ne suffit donc pas
 * à lire le mot de passe SMTP.
 */
final class SecretCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private string $key;

    public function __construct(string $secret)
    {
        $this->key = hash('sha256', $secret, true);
    }

    public function encrypt(?string $plainText): ?string
    {
        if (null === $plainText || '' === $plainText) {
            return null;
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $cipherText = openssl_encrypt(
            $plainText,
            self::CIPHER,
            $this->key,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if (false === $cipherText) {
            throw new SecretCipherException('Impossible de chiffrer le secret.');
        }

        return base64_encode($iv.$tag.$cipherText);
    }

    public function decrypt(?string $payload): ?string
    {
        if (null === $payload || '' === $payload) {
            return null;
        }

        $decoded = base64_decode($payload, true);
        if (false === $decoded || \strlen($decoded) <= self::IV_LENGTH + self::TAG_LENGTH) {
            throw new SecretCipherException('Secret chiffré invalide.');
        }

        $iv = substr($decoded, 0, self::IV_LENGTH);
        $tag = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
        $cipherText = substr($decoded, self::IV_LENGTH + self::TAG_LENGTH);

        $plainText = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            $this->key,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if (false === $plainText) {
            throw new SecretCipherException('Le secret enregistré ne peut pas être déchiffré (secret applicatif modifié ?).');
        }

        return $plainText;
    }
}