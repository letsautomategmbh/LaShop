<?php

namespace Modules\LaStore\Support;

/**
 * Format und Pruefung des Lizenzschluessels.
 *
 *     LA-4K7QM-9XR2T-B8VN3-HW6YD-J1ZP5
 *
 * Fuenf Gruppen zu fuenf Zeichen aus Crockford-Base32. Das Alphabet laesst
 * I, L, O und U aus - I/L/O verwechselt man mit 1/0, und U faellt weg, damit
 * kein Schluessel versehentlich ein Schimpfwort bildet.
 *
 * Die letzten ZWEI Zeichen sind eine positionsgewichtete Pruefsumme ueber die
 * 23 davor, modulo 1024. Damit faellt ein verschriebener Schluessel sofort auf,
 * ohne Serveraufruf - und die Aktivierungsgrenze von zehn Versuchen je Stunde
 * wird nicht von Tippfehlern aufgebraucht.
 *
 * Warum zwei Zeichen und nicht eines: mit einem einzelnen Zeichen waere der
 * Modulus 32. Ein Einzelfehler aendert die Summe um (Position x Differenz),
 * und das wird bei 32 in 44 Faellen genau null - etwa Position 2 mit
 * Differenz 16. Solche Tippfehler gingen still als gueltig durch. Bei 1024
 * ist die groesstmoegliche Aenderung 23 x 31 = 713 und damit kleiner als der
 * Modulus, kann also nie auf null umschlagen. Ein Nachbartausch aendert die
 * Summe immer um genau die Differenz der beiden Zeichen, Betrag hoechstens 31.
 * Beide Fehlerarten sind dadurch nicht nur wahrscheinlich, sondern
 * nachweislich immer erkannt - siehe LicenseKeyTest.
 */
class LicenseKey
{
    const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    const PREFIX = 'LA';
    const GROUPS = 5;
    const GROUP_LEN = 5;
    const CHECK_LEN = 2;
    const MODULUS = 1024;

    /**
     * Eingabe des Benutzers auf die 25 Nutzzeichen bringen.
     *
     * Grosszuegig beim Lesen: Leerzeichen, Bindestriche und ein vorangestelltes
     * LA- werden entfernt, Kleinbuchstaben angehoben, und die typischen
     * Verwechslungen O->0, I->1, L->1 werden korrigiert. U wird NICHT ersetzt,
     * sondern gilt als ungueltig - es gehoert nicht ins Alphabet, und ein
     * stilles Umbiegen wuerde einen falschen Schluessel gueltig aussehen lassen.
     *
     * @param string $input
     *
     * @return string
     */
    public static function normalize($input)
    {
        $s = strtoupper(trim((string) $input));
        $s = preg_replace('/[^A-Z0-9]/', '', $s);

        if (strpos($s, self::PREFIX) === 0) {
            $s = substr($s, strlen(self::PREFIX));
        }

        return strtr($s, array('O' => '0', 'I' => '1', 'L' => '1'));
    }

    /**
     * @param string $input
     *
     * @return bool
     */
    public static function isValid($input)
    {
        $s = self::normalize($input);

        if (strlen($s) !== self::GROUPS * self::GROUP_LEN) {
            return false;
        }

        if (strspn($s, self::ALPHABET) !== strlen($s)) {
            return false;
        }

        return substr($s, -self::CHECK_LEN) === self::checksum(substr($s, 0, -self::CHECK_LEN));
    }

    /**
     * Die Anzeigeform mit Praefix und Bindestrichen.
     *
     * @param string $input
     *
     * @return string
     */
    public static function format($input)
    {
        $s = self::normalize($input);

        return self::PREFIX.'-'.implode('-', str_split($s, self::GROUP_LEN));
    }

    /**
     * Die ersten acht Zeichen der Anzeigeform, wie sie im Portal und in
     * Protokollen steht. Nie der ganze Schluessel - der gehoert nirgends hin,
     * wo er mitgelesen werden koennte.
     *
     * @param string $input
     *
     * @return string
     */
    public static function prefix($input)
    {
        return substr(self::format($input), 0, 8);
    }

    /**
     * Positionsgewichtete Pruefsumme ueber die 23 Nutzzeichen, als zwei
     * Zeichen des Alphabets (10 Bit, modulo 1024).
     *
     * @param string $data 23 Zeichen aus dem Alphabet
     *
     * @return string zwei Zeichen, oder '' bei ungueltiger Eingabe
     */
    public static function checksum($data)
    {
        $sum = 0;
        $len = strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $value = strpos(self::ALPHABET, $data[$i]);

            if ($value === false) {
                return '';
            }

            $sum += ($i + 1) * $value;
        }

        $sum %= self::MODULUS;

        return self::ALPHABET[($sum >> 5) & 31].self::ALPHABET[$sum & 31];
    }

    /**
     * Nur fuer Tests und die spaetere Server-Seite: einen gueltigen Schluessel
     * erzeugen. Verwendet random_bytes, nicht rand() - ein vorhersagbarer
     * Schluesselraum waere die teuerste denkbare Abkuerzung.
     *
     * @return string
     */
    public static function generate()
    {
        $data = '';

        for ($i = 0; $i < self::GROUPS * self::GROUP_LEN - self::CHECK_LEN; $i++) {
            $data .= self::ALPHABET[random_int(0, 31)];
        }

        return self::format($data.self::checksum($data));
    }
}
