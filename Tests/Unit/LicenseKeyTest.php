<?php

namespace Modules\LaStore\Tests\Unit;

use Modules\LaStore\Support\LicenseKey;
use PHPUnit\Framework\TestCase;

class LicenseKeyTest extends TestCase
{
    public function testGeneratedKeysAreValid()
    {
        for ($i = 0; $i < 500; $i++) {
            $key = LicenseKey::generate();

            $this->assertTrue(LicenseKey::isValid($key), 'Erzeugt, aber ungueltig: '.$key);
            $this->assertMatchesRegularExpression('/^LA(-[0-9A-Z]{5}){5}$/', $key);
        }
    }

    public function testAcceptsSloppyInput()
    {
        $key = LicenseKey::generate();
        $plain = LicenseKey::normalize($key);

        $this->assertTrue(LicenseKey::isValid(strtolower($key)));
        $this->assertTrue(LicenseKey::isValid($plain));
        $this->assertTrue(LicenseKey::isValid(' '.$key.' '));
        $this->assertTrue(LicenseKey::isValid(str_replace('-', ' ', $key)));
    }

    public function testConfusableCharactersAreCorrected()
    {
        // Wer O statt 0 oder I/L statt 1 abschreibt, soll trotzdem durchkommen.
        $this->assertSame('0', LicenseKey::normalize('O'));
        $this->assertSame('1', LicenseKey::normalize('I'));
        $this->assertSame('1', LicenseKey::normalize('L'));

        // U gehoert nicht ins Alphabet und wird NICHT stillschweigend umgebogen.
        $this->assertSame('U', LicenseKey::normalize('U'));
        $this->assertFalse(LicenseKey::isValid('LA-UUUUU-UUUUU-UUUUU-UUUUU-UUUUU'));
    }

    public function testRejectsWrongLength()
    {
        $this->assertFalse(LicenseKey::isValid(''));
        $this->assertFalse(LicenseKey::isValid('LA-4K7QM'));
        $this->assertFalse(LicenseKey::isValid(LicenseKey::generate().'X'));
    }

    /**
     * Der eigentliche Zweck der Pruefziffer: ein einzelner vertippter
     * Buchstabe darf nie als gueltig durchgehen.
     */
    public function testSingleCharacterTyposAreCaught()
    {
        $alphabet = LicenseKey::ALPHABET;
        $checked = 0;

        for ($n = 0; $n < 40; $n++) {
            $plain = LicenseKey::normalize(LicenseKey::generate());

            for ($pos = 0; $pos < strlen($plain); $pos++) {
                for ($a = 0; $a < 32; $a++) {
                    if ($alphabet[$a] === $plain[$pos]) {
                        continue;
                    }

                    $typo = $plain;
                    $typo[$pos] = $alphabet[$a];

                    $this->assertFalse(LicenseKey::isValid($typo), 'Tippfehler nicht erkannt: '.$typo);
                    $checked++;
                }
            }
        }

        $this->assertGreaterThan(28000, $checked);
    }

    /**
     * Der zweithaeufigste Abschreibfehler: zwei benachbarte Zeichen vertauscht.
     * Eine ungewichtete Quersumme wuerde das komplett uebersehen.
     */
    public function testAdjacentTranspositionsAreCaught()
    {
        $missed = 0;
        $total = 0;

        for ($n = 0; $n < 200; $n++) {
            $plain = LicenseKey::normalize(LicenseKey::generate());

            for ($pos = 0; $pos < strlen($plain) - 1; $pos++) {
                if ($plain[$pos] === $plain[$pos + 1]) {
                    continue;
                }

                $swapped = $plain;
                $swapped[$pos] = $plain[$pos + 1];
                $swapped[$pos + 1] = $plain[$pos];

                $total++;

                if (LicenseKey::isValid($swapped)) {
                    $missed++;
                }
            }
        }

        $this->assertGreaterThan(3000, $total);
        $this->assertSame(0, $missed, $missed.' von '.$total.' Zahlendrehern nicht erkannt');
    }

    public function testPrefixIsShortAndStable()
    {
        $key = 'LA-4K7QM-9XR2T-B8VN3-HW6YD-J1ZP5';

        $this->assertSame('LA-4K7QM', LicenseKey::prefix($key));
        $this->assertSame('LA-4K7QM', LicenseKey::prefix(strtolower(str_replace('-', '', $key))));
    }
}
