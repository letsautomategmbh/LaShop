<?php

namespace Modules\LaStore\Support;

/**
 * Prueft, ob ein heruntergeladenes Paket echt ist.
 *
 * Zwei Fragen, in dieser Reihenfolge:
 *
 * 1. Ist die Datei die, deren Hash der Server nennt?
 * 2. Stammt dieser Hash von uns - also ist er mit einem Schluessel signiert,
 *    dessen oeffentliche Haelfte in diesem Modul steht?
 *
 * Signiert wird der HASH, nicht das Archiv. Das ist kein Sparen: der Hash ist
 * 64 Zeichen und die Signatur damit unabhaengig von der Paketgroesse. Und es
 * heisst, dass die Pruefung in zwei Schritte zerfaellt, die getrennt
 * fehlschlagen koennen - man weiss danach, WAS nicht stimmte.
 *
 * Der Zweck der ganzen Klasse: ohne sie installiert FreeScouts eigener
 * Updater alles, was unter der erwarteten Adresse liegt. Wer den
 * Auslieferungsweg unterwandert, bekommt seinen Code auf jede Installation,
 * auf der automatische Updates stehen. Die Signatur allein verhindert das
 * nicht - sie muss auch geprueft werden, und genau das ist hier.
 */
class PackageVerifier
{
    const OK = 'ok';
    const UNREADABLE = 'unreadable';
    const HASH_MISMATCH = 'hash_mismatch';
    const MISSING_SIGNATURE = 'missing_signature';
    const UNKNOWN_KEY = 'unknown_key';
    const BAD_SIGNATURE = 'bad_signature';

    /** @var string */
    protected $status;

    /** @var string */
    protected $kid;

    protected function __construct($status, $kid = '')
    {
        $this->status = $status;
        $this->kid = (string) $kid;
    }

    /**
     * @param string $file          Pfad zur heruntergeladenen Datei
     * @param string $expectedHash  sha256 in Hexadezimal, wie der Server ihn nennt
     * @param string $signature     base64, Signatur ueber den Hash
     * @param string     $kid       Kennung des Schluessels
     * @param array|null $keys      Nur fuer Tests: Schluessel statt PublicKeys::all()
     *
     * @return self
     */
    /*
     * ?array und nicht "array ... = null": der implizit nullbare Parameter ist
     * ab PHP 8.4 verworfen, und FreeScout macht aus E_DEPRECATED eine
     * Ausnahme - der Aufruf starb also, nicht nur das Protokoll. Gefunden
     * beim Nachstellen des echten Pfads, nicht in den Tests: PHPUnit meldet
     * die Verwerfung nur, HandleExceptions wirft sie. ?array traegt ab
     * PHP 7.1, also die ganze Spanne, die FreeScout abdeckt.
     */
    public static function check($file, $expectedHash, $signature, $kid, ?array $keys = null)
    {
        if (!is_string($file) || !is_file($file) || !is_readable($file)) {
            return new self(self::UNREADABLE);
        }

        $expectedHash = strtolower(trim((string) $expectedHash));

        if ($expectedHash === '') {
            // Kein Hash heisst: nicht pruefbar. Und nicht pruefbar heisst
            // nicht installieren - nicht "wohl in Ordnung".
            return new self(self::HASH_MISMATCH);
        }

        $tatsaechlich = hash_file('sha256', $file);

        // hash_equals und nicht ===: hier ist die Laufzeit zwar kein Geheimnis,
        // aber die Gewohnheit soll stimmen. An der Stelle, an der es zaehlt
        // (Signaturen, Token), ist derselbe Aufruf zwingend.
        if (!is_string($tatsaechlich) || !hash_equals($expectedHash, strtolower($tatsaechlich))) {
            return new self(self::HASH_MISMATCH);
        }

        if (!is_string($signature) || trim($signature) === '') {
            return new self(self::MISSING_SIGNATURE);
        }

        // Die Schluessel als Parameter und NICHT ueber einen globalen
        // Umschalter: eine statische Variable, die Tests umbiegen, ist eine
        // Variable, die auch im Betrieb umgebogen werden kann.
        //
        // Beide Wege laufen durch PublicKeys::pick(), damit die Zweckpruefung
        // in genau EINER Stelle steht. Vorher pruefte der Parameterweg sie
        // nicht - und der Test, der die Trennung beweisen sollte, ging
        // deshalb durch.
        $key = $keys === null
            ? PublicKeys::find((string) $kid, 'package')
            : PublicKeys::pick($keys, $kid, 'package');

        if ($key === null) {
            // Ein unbekanntes kid ist der Normalfall nach einem
            // Schluesselwechsel: der Server signiert schon mit dem neuen, das
            // Modul kennt nur den alten. Die Meldung muss darum zum Update
            // des MODULS fuehren, nicht zu einer Sicherheitswarnung.
            return new self(self::UNKNOWN_KEY, $kid);
        }

        $algo = PublicKeys::opensslAlgo(isset($key['algo']) ? $key['algo'] : 'RS256');

        if ($algo === null) {
            return new self(self::UNKNOWN_KEY, $kid);
        }

        $roh = base64_decode((string) $signature, true);

        if ($roh === false) {
            return new self(self::BAD_SIGNATURE, $kid);
        }

        // Signiert wurde der HEXADEZIMALE Hash als Zeichenkette - genau so,
        // wie hash_file ihn liefert und wie der Server ihn in sign() gibt.
        // Wer hier die rohen Bytes einsetzt, bekommt eine Signatur, die
        // niemals passt, und sucht den Fehler beim Schluessel.
        if (openssl_verify($expectedHash, $roh, $key['pem'], $algo) !== 1) {
            return new self(self::BAD_SIGNATURE, $kid);
        }

        return new self(self::OK, $kid);
    }

    /** @return bool */
    public function passed()
    {
        return $this->status === self::OK;
    }

    /** @return string */
    public function status()
    {
        return $this->status;
    }

    /** @return string */
    public function kid()
    {
        return $this->kid;
    }


    /**
     * Was der Betreiber lesen soll. Nicht der Statuscode.
     *
     * @return string
     */
    public function explain()
    {
        switch ($this->status) {
            case self::OK:
                return Text::get('Das Paket ist unverändert und mit unserem Schlüssel signiert.');

            case self::UNREADABLE:
                return Text::get('Die heruntergeladene Datei ist nicht lesbar. Der Download ist abgebrochen — bitte erneut versuchen.');

            case self::HASH_MISMATCH:
                return Text::get('Die heruntergeladene Datei stimmt nicht mit der Angabe des Shops überein. Sie wird nicht installiert. Das kann ein abgebrochener Download sein — oder ein verändertes Paket.');

            case self::MISSING_SIGNATURE:
                return Text::get('Zu dieser Version gibt es keine Signatur. Sie wird nicht installiert.');

            case self::UNKNOWN_KEY:
                return Text::get('Diese Version ist mit einem Schlüssel signiert, den dieses Modul nicht kennt (:kid). LaShop selbst braucht ein Update.', ['kid' => $this->kid]);

            case self::BAD_SIGNATURE:
                return Text::get('Die Signatur des Pakets ist ungültig. Es wird nicht installiert.');
        }

        return Text::get('Unbekannter Prüfstand.');
    }
}
