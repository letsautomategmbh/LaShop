<?php

namespace Modules\LaStore\Support;

use Modules\LaStore\Entities\CatalogEntry;
use Modules\LaStore\Entities\License;

/**
 * Bestandsaufnahme: was ist installiert, was davon steht im Katalog, was hat
 * eine Lizenz - und was traegt noch Zugangsdaten in der module.json.
 *
 * Der erste Fall, der beim Umstieg real eintritt: eine Installation, auf der
 * die Module schon laufen und sich bisher ueber ein eingebettetes Token
 * aktualisiert haben. Sie sollen ununterbrochen weiterlaufen; es aendert sich
 * nur, woher die Updates kommen.
 */
class InstalledModules
{
    const STATE_LICENSED = 'licensed';
    const STATE_ADOPTABLE = 'adoptable';
    const STATE_FOREIGN = 'foreign';
    const STATE_AVAILABLE = 'available';

    /**
     * Eine Zeile je Modul und je Katalogeintrag.
     *
     * @return array
     */
    /**
     * Die Detailseite eines Moduls im LaShop.
     *
     * Die Beschreibung, die Bildschirmbilder und das Aenderungsprotokoll
     * stehen dort - und nur dort. Eine zweite Fassung im Modul zu pflegen
     * hiess, zwei Wahrheiten zu haben; FreeScout verweist bei seinen eigenen
     * Modulen aus demselben Grund nach draussen ("Details ansehen").
     *
     * An einer Stelle und nicht in der Ansicht zusammengesetzt: sonst steht
     * die Pfadform an jeder Verwendung neu da, und eine Aenderung am Shop
     * findet nur die Haelfte.
     */
    public static function shopUrl($alias)
    {
        $basis = rtrim((string) config('lastore.shop_url'), '/');

        return $basis.'/produkt/'.rawurlencode((string) $alias);
    }

    public static function inventory()
    {
        $catalog = CatalogEntry::all()->keyBy('alias');
        $licenses = License::all()->keyBy('product_alias');
        $rows = array();

        foreach (\Module::all() as $module) {
            $alias = $module->getAlias();

            if ($alias === 'lastore') {
                continue;
            }

            $entry = $catalog->get($alias);
            $license = $licenses->get($alias);

            $rows[$alias] = array(
                'alias'       => $alias,
                // Der Name aus dem Katalog gewinnt: der Kunde kauft
                // "Invoicing", auf der Platte heisst das Modul intern
                // "Activities". Ohne Katalogeintrag bleibt der interne Name -
                // besser als gar keiner.
                'name'        => $entry ? $entry->value('name', $module->getName()) : $module->getName(),
                'local_name'  => $module->getName(),
                'installed'   => (string) $module->get('version'),
                'active'      => (bool) $module->active(),
                'available'   => $entry ? $entry->value('version') : null,
                'in_catalog'  => (bool) $entry,
                'license'     => $license,
                'credentials' => self::credentialKind($module),
                'state'       => self::state($entry, $license),
                /*
                 * Das Sinnbild aus der module.json des installierten Moduls -
                 * genau die Quelle, aus der FreeScout es auf seiner eigenen
                 * Seite nimmt (`$module->get('img')`). Es ist dort eine
                 * base64-Datenadresse, also gross (rund 80-100 kB je Modul),
                 * aber kein zusaetzlicher Abruf.
                 *
                 * Der Katalog nur als Rueckfall: sein Bild ist fuer den Laden
                 * gedacht und muss nicht dasselbe Format haben. Fuer ein
                 * Modul, das hier liegt, ist die module.json die naehere
                 * Wahrheit - und sie ist das, was der Kunde in FreeScouts
                 * Modulliste daneben sieht.
                 */
                'img'         => $module->get('img') ?: ($entry ? $entry->value('img') : null),
                'summary'     => $entry ? $entry->value('summary') : (string) $module->getDescription(),
                'details_url' => $entry ? self::shopUrl($alias) : null,
            );
        }

        // Katalogeintraege, die hier nicht installiert sind - damit die Liste
        // auch zeigt, was es noch gaebe.
        foreach ($catalog as $alias => $entry) {
            if (isset($rows[$alias])) {
                continue;
            }

            $rows[$alias] = array(
                'alias'       => $alias,
                'name'        => $entry->value('name', $alias),
                'installed'   => null,
                'active'      => false,
                'available'   => $entry->value('version'),
                'in_catalog'  => true,
                'license'     => $licenses->get($alias),
                'credentials' => null,
                'state'       => self::STATE_AVAILABLE,
                'img'         => $entry->value('img'),
                'summary'     => $entry->value('summary'),
                'details_url' => self::shopUrl($alias),
            );
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * Nur die, die uebernommen werden koennen: installiert, im Katalog, ohne
     * Lizenz.
     *
     * @return array
     */
    public static function adoptable()
    {
        $adoptable = array();

        foreach (self::inventory() as $row) {
            if ($row['state'] === self::STATE_ADOPTABLE) {
                $adoptable[] = $row;
            }
        }

        return $adoptable;
    }

    /**
     * Welche Module noch Zugangsdaten in der module.json tragen.
     *
     * Das ist der Grund, warum die Uebernahme ueberhaupt gemacht wird: erst
     * wenn alle Kunden umgestellt sind, darf das Token aus den Repositories
     * verschwinden. Bis dahin steht es in jeder ausgelieferten module.json -
     * und in deren Git-Historie.
     *
     * @return array
     */
    public static function withCredentials()
    {
        $found = array();

        foreach (self::inventory() as $row) {
            if ($row['credentials']) {
                $found[] = $row;
            }
        }

        return $found;
    }

    protected static function state($entry, $license)
    {
        if ($license && self::lizenzGilt($license)) {
            return self::STATE_LICENSED;
        }

        /*
         * Ohne GUELTIGE Lizenz ist ein Modul, das im Katalog steht, wieder
         * "zu uebernehmen" - der Verwalter braucht ein Schluesselfeld.
         *
         * Vorher stand hier nur `if ($license)`: die bloosse EXISTENZ einer
         * Zeile genuegte. Eine abgelaufene Lizenz zeigte damit weiter das
         * gruene "Lizenziert" und keinen Knopf - waehrend daneben das
         * Ablaufdatum in Rot stand. Zwei Angaben, die sich widersprachen.
         */
        return $entry ? self::STATE_ADOPTABLE : self::STATE_FOREIGN;
    }

    /**
     * Gilt die Lizenz noch?
     *
     * `grace_until` ist das tatsaechliche Ende: bis dahin laeuft das Modul,
     * auch wenn `valid_until` schon vorbei ist (Gnadenfrist). Ist es
     * verstrichen, ist Schluss - und das sticht den gespiegelten Zustand,
     * denn der wird nur bei einer Pruefung neu geschrieben, waehrend ein
     * Datum von selbst verstreicht.
     *
     * Andernfalls zaehlt nur ein Zustand, der aus einer BESTANDENEN Pruefung
     * stammt: OK oder Gnadenfrist.
     *
     * Vorher stand hier die Umkehrung - gueltig, solange nicht ausdruecklich
     * "abgelaufen". Der Gedanke war, ein nie geprueftes Feld solle keine
     * bezahlte Lizenz entwerten. Nur ist der Vorgabewert der Spalte
     * "unknown", und die Beschriftungsliste nennt ihn "Nicht geprueft": eine
     * Zeile, die nie eine gueltige Signatur gesehen hat, bekam damit das
     * gruene "Lizenziert". Auch "Signatur ungueltig" und "Unbekannter
     * Schluessel" galten so als gueltig.
     *
     * Nicht geprueft ist nicht lizenziert. Und syncFrom() schreibt bei jeder
     * bestandenen Pruefung einen echten Status - eine Zeile bleibt also nur
     * dann auf "unknown", wenn es nie eine gab.
     */
    private static function lizenzGilt($license)
    {
        if ($license->grace_until !== null && $license->grace_until->isPast()) {
            return false;
        }

        return in_array($license->status, array(
            \Modules\LaStore\Support\LicenseToken::OK,
            \Modules\LaStore\Support\LicenseToken::GRACE,
        ), true);
    }

    /**
     * Die ART der Zugangsdaten, nie die Zugangsdaten selbst. Sie hier
     * auszugeben hiesse, sie in Protokolle und Bildschirmfotos zu tragen.
     *
     * @return string|null
     */
    protected static function credentialKind($module)
    {
        $path = $module->getPath().'/module.json';

        if (!is_file($path)) {
            return null;
        }

        $raw = (string) file_get_contents($path);

        if (preg_match('/github_pat_|ghp_/', $raw)) {
            return 'GitHub-Token';
        }

        if (preg_match('#://[^/@\s"]+@#', $raw)) {
            return 'Zugangsdaten in der URL';
        }

        return null;
    }

    /**
     * @return array
     */
    public static function stateLabels()
    {
        return array(
            self::STATE_LICENSED  => array('success', 'Lizenziert'),
            self::STATE_ADOPTABLE => array('warning', 'Zu übernehmen'),
            self::STATE_AVAILABLE => array('default', 'Nicht installiert'),
            self::STATE_FOREIGN   => array('default', 'Nicht aus dem Store'),
        );
    }
}
