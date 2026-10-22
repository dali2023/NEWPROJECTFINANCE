<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Settings\SecretCipher;
use App\Service\Settings\SecretCipherException;
use PHPUnit\Framework\TestCase;

final class SecretCipherTest extends TestCase
{
    public function testSecretCanBeEncryptedAndDecrypted(): void
    {
        $cipher = new SecretCipher('secret-de-test');

        $encrypted = $cipher->encrypt('Mot de passe SMTP !');

        self::assertNotNull($encrypted);
        self::assertNotSame('Mot de passe SMTP !', $encrypted);
        self::assertSame('Mot de passe SMTP !', $cipher->decrypt($encrypted));
    }

    public function testEmptySecretIsStoredAsNull(): void
    {
        $cipher = new SecretCipher('secret-de-test');

        self::assertNull($cipher->encrypt(null));
        self::assertNull($cipher->encrypt(''));
        self::assertNull($cipher->decrypt(null));
        self::assertNull($cipher->decrypt(''));
    }

    public function testTamperedSecretIsRejected(): void
    {
        $cipher = new SecretCipher('secret-de-test');
        $encrypted = (string) $cipher->encrypt('Mot de passe SMTP !');

        $tampered = substr($encrypted, 0, -2).(str_ends_with($encrypted, 'AA') ? 'BB' : 'AA');

        $this->expectException(SecretCipherException::class);
        $cipher->decrypt($tampered);
    }
}