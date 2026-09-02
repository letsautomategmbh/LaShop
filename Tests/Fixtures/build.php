<?php
/**
 * Erzeugt die abgelegten Antworten unter Tests/Fixtures/api.
 *
 * Sie sind echte Antworten im Format der Spezifikation, mit wirklich
 * signierten Tokens - der Client prueft sie also genauso scharf wie spaeter
 * die des Servers. Braucht den privaten Entwicklungsschluessel aus
 * Tests/Fixtures/keys.
 *
 *     php Modules/LaStore/Tests/Fixtures/build.php
 */

require __DIR__.'/../../Support/LicenseToken.php';
require __DIR__.'/../../Support/LicenseKey.php';
require __DIR__.'/TokenFactory.php';

use Modules\LaStore\Support\LicenseKey;
use Modules\LaStore\Tests\Fixtures\TokenFactory;

// Fest, nicht gewuerfelt: ein vorsigniertes Token traegt die Installation in
// "sub" und der Client prueft das. Eine wechselnde ID wuerde jede Pruefung
// scheitern lassen - zu Recht.
const INSTALLATION = 'b3f1c8a2-4e7d-4b19-9f30-2a6c5d81e044';

$dir = __DIR__.'/api';
@mkdir($dir, 0775, true);

function put($dir, $name, array $data)
{
    file_put_contents(
        $dir.'/'.$name,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
    );
    echo str_pad($name, 42), filesize($dir.'/'.$name), " Bytes\n";
}

function key_from($data)
{
    return LicenseKey::format($data.LicenseKey::checksum($data));
}

// Weit in der Zukunft, damit die Entwicklungsumgebung nicht naechstes Jahr
// an abgelaufenen Fixtures scheitert.
$far = mktime(0, 0, 0, 1, 1, 2031);
$past = mktime(0, 0, 0, 1, 1, 2025);

$products = [
    [
        'alias' => 'bexio', 'name' => 'Bexio',
        'summary' => 'Kontakte, Artikel und Rechnungen aus Bexio direkt im Ticketsystem.',
        'kind' => 'freescout_module', 'version' => '2.15.0',
        'min_app_version' => '1.8.220', 'min_php' => '7.1',
        'requires' => new stdClass(),
        'prices' => [['license_type' => 'yearly', 'currency' => 'CHF', 'unit_amount' => 24000, 'per_user' => false]],
    ],
    [
        'alias' => 'bexiosubscriptions', 'name' => 'Bexio Abonnemente',
        'summary' => 'Abonnemente und Verträge für Bexio-Kunden verwalten und wiederkehrend abrechnen.',
        'kind' => 'freescout_module', 'version' => '1.5.0',
        'min_app_version' => '1.8.220', 'min_php' => '7.1',
        'requires' => ['bexio' => '2.15.0'],
        'prices' => [['license_type' => 'yearly_per_user', 'currency' => 'CHF', 'unit_amount' => 900, 'per_user' => true]],
    ],
    [
        'alias' => 'invoicing', 'name' => 'Invoicing',
        'summary' => 'Projekte, Aufgaben, Zeiterfassung und Rechnungsstellung.',
        'kind' => 'freescout_module', 'version' => '1.31.0',
        'min_app_version' => '1.8.220', 'min_php' => '7.1',
        'requires' => new stdClass(),
        'prices' => [['license_type' => 'one_time', 'currency' => 'CHF', 'unit_amount' => 39000, 'per_user' => false]],
    ],
    [
        'alias' => 'shurl', 'name' => 'shurl.ch',
        'summary' => 'Kurz-URLs mit eigener Domain. Kein FreeScout-Modul — Download und Schlüssel.',
        'kind' => 'standalone', 'version' => '3.2.0',
        'min_app_version' => null, 'min_php' => '8.1',
        'requires' => new stdClass(),
        'prices' => [['license_type' => 'monthly', 'currency' => 'CHF', 'unit_amount' => 1500, 'per_user' => false]],
    ],
];

put($dir, 'catalog.json', ['ok' => true, 'products' => $products]);

foreach ($products as $p) {
    put($dir, 'catalog.'.$p['alias'].'.json', ['ok' => true, 'product' => $p]);
}

put($dir, 'installations.json', [
    'ok' => true,
    'installation_id' => INSTALLATION,
    'secret' => 'si_9Xr2TB8vN3hW6yDJ1zP5',
    'heartbeat_hours' => 24,
]);

// Drei Schluessel, die den Client durch drei verschiedene Zustaende fuehren.
$licenses = [
    [
        'key' => key_from('4K7QM9XR2TB8VN3HW6YDJ1Z'),
        'product_alias' => 'bexiosubscriptions',
        'claims' => ['typ' => 'yearly_per_user', 'seats' => 25, 'exp' => $far, 'grc' => $far + 30 * 86400, 'upd' => $far],
        'license' => ['type' => 'yearly_per_user', 'seats' => 25, 'releases_left' => 2],
    ],
    [
        'key' => key_from('7T2WPFH4M9NQ3XKD8BVR5YJ'),
        'product_alias' => 'invoicing',
        'claims' => ['typ' => 'one_time', 'seats' => null, 'exp' => $far, 'grc' => $far, 'upd' => mktime(0, 0, 0, 1, 1, 2028)],
        'license' => ['type' => 'one_time', 'seats' => null, 'releases_left' => 1],
    ],
    [
        // Abgelaufen, damit der Weg "Gnadenfrist vorbei" ohne Zeitreise
        // ausprobierbar ist.
        'key' => key_from('9WQ2NR6TB4KHX3MJ85DFYPV'),
        'product_alias' => 'bexio',
        'claims' => ['typ' => 'yearly', 'seats' => null, 'exp' => $past, 'grc' => $past + 30 * 86400, 'upd' => $past],
        'license' => ['type' => 'yearly', 'seats' => null, 'releases_left' => 0],
    ],
];

$out = [];
$tokens = [];

foreach ($licenses as $l) {
    $token = TokenFactory::make(array_merge([
        'sub' => INSTALLATION,
        'aud' => $l['product_alias'],
        'lic' => substr(hash('sha256', LicenseKey::normalize($l['key'])), 0, 16),
        'iat' => time(),
    ], $l['claims']));

    $tokens[$l['product_alias']] = $token;

    $out[] = [
        'key' => $l['key'],
        'product_alias' => $l['product_alias'],
        'response' => [
            'ok' => true,
            'status' => 'valid',
            'token' => $token,
            'license' => array_merge($l['license'], [
                'valid_until' => date('Y-m-d', $l['claims']['exp']),
                'updates_until' => date('Y-m-d', $l['claims']['upd']),
            ]),
        ],
    ];
}

put($dir, '_licenses.json', ['ok' => true, 'licenses' => $out]);
put($dir, 'licenses.check-batch.json', ['ok' => true, 'tokens' => $tokens, 'statuses' => new stdClass()]);
put($dir, 'heartbeat.json', ['ok' => true, 'next_in_hours' => 24, 'notices' => []]);

foreach ($products as $p) {
    if ($p['kind'] !== 'freescout_module') {
        continue;
    }

    put($dir, 'packages.'.$p['alias'].'.latest.json', [
        'ok' => true,
        'version' => $p['version'],
        'released_at' => date('c'),
        'min_app_version' => $p['min_app_version'],
        'min_php' => $p['min_php'],
        'requires' => $p['requires'],
        'sha256' => str_repeat('0', 64),
        'signature' => '',
        'size' => 0,
        'download' => '',
        'expires_at' => date('c', time() + 900),
        'changelog' => 'Abgelegte Antwort ohne echtes Paket — Schritt B kennt noch keinen Server.',
    ]);
}

echo "\nSchlüssel für die Entwicklungsumgebung:\n";
foreach ($licenses as $l) {
    echo '  ', str_pad($l['product_alias'], 20), $l['key'], "\n";
}
