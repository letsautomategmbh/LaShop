<?php

/**
 * Durchlauf des Clients gegen einen ECHTEN Lizenzserver.
 *
 * Ergaenzt smoke.php, das gegen abgelegte Antworten laeuft: hier wird das
 * Token wirklich vom Server signiert und vom Client wirklich geprueft.
 *
 *   docker compose exec -u nginx -w /www/html freescout \
 *       php Modules/LaStore/Tests/Manual/against_server.php --force <schluessel>
 */

$root = getenv('FS_ROOT') ?: '/www/html';

require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$args = array_slice($argv, 1);

if (!in_array('--force', $args, true)) {
    echo 'Abbruch: dieses Skript aktiviert Lizenzen gegen einen echten Server. Mit --force starten.'.PHP_EOL;
    exit(1);
}

$key = null;

foreach ($args as $arg) {
    if ($arg !== '--force') {
        $key = $arg;
    }
}

if (!$key) {
    echo 'Kein Lizenzschlüssel übergeben.'.PHP_EOL;
    exit(1);
}

use Modules\LaStore\Entities\Installation;
use Modules\LaStore\Entities\License;
use Modules\LaStore\Services\LicenseService;
use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\LicenseToken;

$failures = 0;
$checks = 0;

function check($label, $condition, $detail = '')
{
    global $failures, $checks;
    $checks++;
    echo($condition ? '  ok    ' : '  FEHLT '), $label, $detail ? '  ('.$detail.')' : '', PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

function expectFailure($label, callable $fn, $expectedCode = null)
{
    try {
        $fn();
        check($label, false, 'kein Fehler geworfen');
    } catch (StoreException $e) {
        check($label, $expectedCode === null || $e->errorCode() === $expectedCode, $e->errorCode().': '.$e->getMessage());
    }
}

echo PHP_EOL, 'Transport: ', config('lastore.transport'), '  ', config('lastore.base_url'), PHP_EOL;
echo 'Installation: ', Installation::current()->installation_id, PHP_EOL, PHP_EOL;

$service = new LicenseService();

echo 'Aktivierung', PHP_EOL;

$license = $service->activate($key, 'bexiosubscriptions');
check('Schlüssel angenommen', $license->status === LicenseToken::OK, $license->status);
check('Sitze aus dem signierten Token', $license->seats === 25, (string) $license->seats);

$claims = LicenseToken::peek($license->token);
check('Token vom echten Server signiert', ($claims['iss'] ?? '') === 'shop.letsautomate.ch', $claims['iss'] ?? '—');
check('Token auf diese Installation ausgestellt', ($claims['sub'] ?? '') === Installation::current()->installation_id);
check('Signaturschlüssel benannt', !empty($claims['kid']), $claims['kid'] ?? '—');

// Der Server stellt kurzlebige Tokens aus und nicht eines ueber die ganze
// Vertragslaufzeit - sonst wirkte ein Widerruf erst in einem Jahr.
$laufzeitTage = (int) round((($claims['exp'] ?? 0) - time()) / 86400);
check('Token ist kurzlebig, nicht vertragslang', $laufzeitTage > 0 && $laufzeitTage <= 60, $laufzeitTage.' Tage');
check('Gnadenfrist liegt dahinter', ($claims['grc'] ?? 0) > ($claims['exp'] ?? 0));

echo PHP_EOL, 'Nachführung', PHP_EOL;

$result = $service->refreshAll();
check('check-batch liefert Zustand', ($result['bexiosubscriptions'] ?? null) === LicenseToken::OK, $result['bexiosubscriptions'] ?? '—');

$after = License::where('product_alias', 'bexiosubscriptions')->first();
$verified = $after->verify(Installation::current());
check('nachgeführtes Token prüft sich lokal', $verified->isUsable(), $verified->status());
check('Prüfzeitpunkt gesetzt', $after->checked_at !== null && $after->checked_at->diffInMinutes(now()) < 2);

// RSA-PKCS#1-v1.5 ist deterministisch: gleiche Angaben, gleiche Signatur.
// Zwei Tokens aus derselben Sekunde sind deshalb byteweise identisch - das
// ist kein Fehler, sondern macht die Nachführung von sich aus idempotent.
$claimsAfter = LicenseToken::peek($after->token);
check('iat nicht rückwärts gelaufen', ($claimsAfter['iat'] ?? 0) >= ($claims['iat'] ?? 0));

echo PHP_EOL, 'Wege, die scheitern müssen', PHP_EOL;

// Formal gueltig, damit der Client ihn nicht schon lokal abfaengt - dieser
// Weg soll wirklich beim Server ankommen.
expectFailure('unbekannter Schlüssel erreicht den Server', function () use ($service) {
    $service->activate('LA-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ-ZZZBC', 'bexiosubscriptions');
}, 'license_unknown');

expectFailure('gültiger Schlüssel, falsches Produkt', function () use ($service, $key) {
    $service->activate($key, 'invoicing');
}, 'product_mismatch');

echo PHP_EOL, str_repeat('-', 60), PHP_EOL;
echo $checks, ' Prüfungen, ', $failures, ' fehlgeschlagen', PHP_EOL;

exit($failures === 0 ? 0 : 1);
