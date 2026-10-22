<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests financiers du cahier des charges (§16, tests A à F).
 *
 * Les montants sont manipulés sous forme de chaînes décimales : aucun test ne
 * passe par un flottant binaire, conformément à l'exigence §5.1.
 */
final class MoneyTest extends TestCase
{
    /**
     * Test B — répartition indirecte : 3 000 répartis 50 / 30 / 20 donnent
     * exactement 1 500 / 900 / 600.
     */
    public function testIndirectAllocationProducesExactShares(): void
    {
        $shares = Money::allocate('3000.000', [
            'projet-a' => '50',
            'projet-b' => '30',
            'projet-c' => '20',
        ]);

        self::assertSame('1500.000', $shares['projet-a']);
        self::assertSame('900.000', $shares['projet-b']);
        self::assertSame('600.000', $shares['projet-c']);
        self::assertSame('3000.000', Money::sum($shares));
    }

    /**
     * L'écart d'arrondi est imputé de façon déterministe et la somme des parts
     * reste strictement égale au montant source (§6.8, REC-008).
     */
    public function testAllocationConservesTotalWhenRoundingIsNeeded(): void
    {
        $shares = Money::allocate('100.000', ['a' => '1', 'b' => '1', 'c' => '1']);

        self::assertSame('33.334', $shares['a']);
        self::assertSame('33.333', $shares['b']);
        self::assertSame('33.333', $shares['c']);
        self::assertSame('100.000', Money::sum($shares));
    }

    /** Test C — avance d'associé : 12 000 − 3 000 = 9 000. */
    public function testPartnerAdvanceRemainingBalance(): void
    {
        self::assertSame('9000.000', Money::sub('12000.000', '3000.000'));
    }

    /**
     * Test D — un transfert interne de 2 000 ne modifie pas le total de
     * trésorerie : seule la répartition entre comptes change.
     */
    public function testInternalTransferIsNeutralOnTotalTreasury(): void
    {
        $before = Money::sum(['15000.000', '5000.000']);

        $source = Money::sub('15000.000', '2000.000');
        $destination = Money::add('5000.000', '2000.000');
        $after = Money::sum([$source, $destination]);

        self::assertSame('13000.000', $source);
        self::assertSame('7000.000', $destination);
        self::assertSame($before, $after);
    }

    /**
     * Test E — résultat d'un projet : 40 000 − 18 000 − 4 000 = 18 000
     * (produit contractuel, charges directes, charges indirectes réparties).
     */
    public function testProjectResultIsExact(): void
    {
        $revenue = '40000.000';
        $directCosts = '18000.000';
        $indirectCosts = '4000.000';

        $result = Money::sub(Money::sub($revenue, $directCosts), $indirectCosts);

        self::assertSame('18000.000', $result);
        self::assertSame('45.00', Money::ratioPercent($result, $revenue));
    }

    /** Test A — encaissement partiel : reste dû = 10 000 − 4 000 = 6 000. */
    public function testPartialReceiptLeavesExpectedBalance(): void
    {
        $invoice = '10000.000';
        $receipt = '4000.000';

        self::assertSame('6000.000', Money::sub($invoice, $receipt));
        self::assertTrue(Money::isPositive(Money::sub($invoice, $receipt)));
    }

    public function testVatAndGrossAmounts(): void
    {
        $ht = '40000.000';
        $rate = '0.1900';

        $vat = Money::mul($ht, $rate);
        $ttc = Money::add($ht, $vat);

        self::assertSame('7600.000', $vat);
        self::assertSame('47600.000', $ttc);
    }

    /** Arrondi commercial demi vers le haut, sans dérive binaire. */
    #[DataProvider('roundingProvider')]
    public function testCommercialRounding(string $input, string $expected): void
    {
        self::assertSame($expected, Money::round($input, 3));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function roundingProvider(): iterable
    {
        yield 'demi vers le haut' => ['1.2345', '1.235'];
        yield 'inférieur' => ['1.2344', '1.234'];
        yield 'négatif' => ['-2.3455', '-2.346'];
        yield 'entier' => ['42', '42.000'];
    }

    public function testNormalizeAcceptsFrenchFormatting(): void
    {
        self::assertSame('1234.500', Money::normalize('1 234,5'));
        self::assertSame('1234.500', Money::normalize("1\u{202F}234,5"));
        self::assertSame('0.000', Money::normalize(''));
        self::assertSame('0.000', Money::normalize(null));
    }

    public function testFormatUsesDecimalStringWithoutFloatCast(): void
    {
        self::assertSame('40 000,000 TND', Money::format('40000.000'));
        self::assertSame('-1 234,500 TND', Money::format('-1234.500'));
        self::assertSame('19 %', Money::formatRate('19.0000'));
    }

    public function testNormalizeRejectsInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::normalize('douze mille');
    }

    public function testDivisionByZeroIsRefused(): void
    {
        $this->expectException(\DivisionByZeroError::class);

        Money::div('100.000', '0.000');
    }
}
