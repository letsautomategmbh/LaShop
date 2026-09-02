<?php

/**
 * End-to-End-Durchlauf der Aktivierung gegen den statischen Endpunkt.
 *
 * Prueft nicht nur den guten Fall, sondern vor allem die, die scheitern
 * muessen: Tippfehler, falsches Produkt, abgelaufene Lizenz, fremde
 * Installation, gefaelschtes Token.
 *
 * Manuelles Dev-Skript, kein PHPUnit-Test: es braucht eine gebootete
 * Applikation und schreibt in die Datenbank.
 *
 *   docker compose exec -u nginx -w /www/html freescout \
 *       php Modules/LaStore/Tests/Manual/smoke.php --force
 *
 * Das -u nginx ist Pflicht: als root angelegte Cache-Dateien brechen
 * anschliessend jeden Request von php-fpm.
 */

$root = getenv('FS_ROOT') ?: '/www/html';

require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!in_array('--force', array_slice($argv, 1), true)) {
    echo 'Abbruch: dieses Skript schreibt Lizenzen in die Datenbank. Mit --force starten.'.PHP_EOL;
    exit(1);
}

use Modules\LaStore\Entities\Installation;
use Modules\LaStore\Entities\License;
use Modules\LaStore\Services\LicenseService;
use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\LicenseToken;
use Modules\LaStore\Tests\Fixtures\TokenFactory;

require __DIR__.'/../Fixtures/TokenFactory.php';

$failures = 0;
$checks = 0;

function check($label, $condition, $detail = '')
{
    global $failures, $checks;
    $checks++;

    if ($condition) {
        echo '  ok    ', $label, $detail ? '  ('.$detail.')' : '', PHP_EOL;
    } else {
        $failures++;
        echo '  FEHLT ', $label, $detail ? '  ('.$detail.')' : '', PHP_EOL;
    }
}

function expectFailure($label, callable $fn, $expectedCode = null)
{
    try {
        $fn();
        check($label, false, 'kein Fehler geworfen');
    } catch (StoreException $e) {
        check($label, $expectedCode === null || $e->errorCode() === $expectedCode,
            $e->errorCode().': '.$e->getMessage());
    }
}

License::query()->delete();

$fixtures = json_decode(file_get_contents(__DIR__.'/../Fixtures/api/_licenses.json'), true);
$keys = [];

foreach ($fixtures['licenses'] as $l) {
    $keys[$l['product_alias']] = $l['key'];
}

$service = new LicenseService();
$installation = $service->register();

echo PHP_EOL, 'Installation: ', $installation->installation_id, PHP_EOL, PHP_EOL;

echo 'Gute Wege', PHP_EOL;

$license = $service->activate($keys['bexiosubscriptions'], 'bexiosubscriptions');
check('gültiger Schlüssel wird angenommen', $license->status === LicenseToken::OK, $license->status);
check('Sitze aus dem Token übernommen', $license->seats === 25, (string) $license->seats);
check('Schlüssel verschlüsselt abgelegt', $license->license_key !== $keys['bexiosubscriptions']);
check('Prefix ist kurz', $license->keyPrefix() === substr($keys['bexiosubscriptions'], 0, 8), $license->keyPrefix());

$again = $service->activate($keys['bexiosubscriptions'], 'bexiosubscriptions');
check('nochmalige Aktivierung legt keine zweite Zeile an', License::where('product_alias', 'bexiosubscriptions')->count() === 1);

$service->activate($keys['invoicing'], 'invoicing');
check('zweite Lizenz danebengelegt', License::count() === 2, License::count().' Zeilen');

echo PHP_EOL, 'Wege, die scheitern müssen', PHP_EOL;

$typo = $keys['bexiosubscriptions'];
$typo[4] = $typo[4] === 'A' ? 'B' : 'A';
expectFailure('Tippfehler wird lokal erkannt, ohne Serveraufruf',
    function () use ($service, $typo) { $service->activate($typo, 'bexiosubscriptions'); }, 'malformed_key');

expectFailure('unbekannter Schlüssel',
    function () use ($service) { $service->activate('LA-00000-00000-00000-00000-00000', 'bexiosubscriptions'); });

expectFailure('Schlüssel für ein anderes Produkt',
    function () use ($service, $keys) { $service->activate($keys['invoicing'], 'bexiosubscriptions'); }, 'product_mismatch');

expectFailure('abgelaufene Lizenz wird nicht übernommen',
    function () use ($service, $keys) { $service->activate($keys['bexio'], 'bexio'); }, LicenseToken::EXPIRED);

check('abgelaufene Lizenz hinterlässt keine Zeile', License::where('product_alias', 'bexio')->count() === 0);

echo PHP_EOL, 'Gefälschte Tokens', PHP_EOL;

$forged = TokenFactory::make(['sub' => $installation->installation_id, 'aud' => 'bexiosubscriptions', 'seats' => 9999]);
$parts = explode('.', $forged);
$claims = json_decode(LicenseToken::b64decode($parts[1]), true);
$claims['seats'] = 100000;
$tampered = $parts[0].'.'.LicenseToken::b64encode(json_encode($claims)).'.'.$parts[2];

expectFailure('manipulierte Sitzzahl wird abgelehnt',
    function () use ($service, $tampered) { $service->importOfflineFile($tampered); }, LicenseToken::BAD_SIGNATURE);

$foreign = TokenFactory::make(['sub' => '00000000-0000-0000-0000-000000000000', 'aud' => 'bexiosubscriptions']);
expectFailure('Token einer fremden Installation wird abgelehnt',
    function () use ($service, $foreign) { $service->importOfflineFile($foreign); }, LicenseToken::WRONG_INSTALLATION);

echo PHP_EOL, 'Offline', PHP_EOL;

$far = mktime(0, 0, 0, 1, 1, 2031);
$offline = TokenFactory::make([
    'sub' => $installation->installation_id, 'aud' => 'invoicing',
    'off' => true, 'exp' => $far, 'grc' => $far,
]);
$imported = $service->importOfflineFile($offline);
check('Offline-Datei übernommen', $imported->status === LicenseToken::OK, $imported->status);
check('Installation auf Offline-Betrieb gestellt', Installation::current()->isOffline());

expectFailure('im Offline-Betrieb geht kein Aufruf mehr raus',
    function () { (new LicenseService())->refreshAll(); }, 'offline_mode');

$back = Installation::current();
$back->mode = Installation::MODE_ONLINE;
$back->save();

echo PHP_EOL, 'Systemuhr', PHP_EOL;

$inst = Installation::current();
$inst->touchMaxSeen(time() + 400 * 86400);
$rolled = License::where('product_alias', 'bexiosubscriptions')->first()->verify(Installation::current());
check('zurückgestellte Uhr wird erkannt', $rolled->status() === LicenseToken::CLOCK_ROLLBACK, $rolled->status());

$inst = Installation::current();
$inst->max_seen_at = null;
$inst->save();

echo PHP_EOL, str_repeat('-', 60), PHP_EOL;
echo $checks, ' Prüfungen, ', $failures, ' fehlgeschlagen', PHP_EOL;

exit($failures === 0 ? 0 : 1);
