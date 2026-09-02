<?php

namespace Modules\LaStore\Services\Transport;

use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\LicenseKey;

/**
 * Ein Transport, der statt eines Servers abgelegte JSON-Dateien liest.
 *
 * Damit steht die ganze Oberflaeche und der Installationsweg, bevor es einen
 * Server gibt - und Fehler im Protokoll fallen jetzt auf, wo sie noch billig
 * sind. Die Antworten sind echte Antworten im Format der Spezifikation,
 * einschliesslich wirklich signierter Tokens.
 *
 * Deshalb ist die Installations-ID hier fest: ein vorsigniertes Token traegt
 * die Installation in "sub", und der Client prueft das. Eine gewuerfelte ID
 * wuerde jede Pruefung scheitern lassen - und zwar zu Recht.
 *
 * Diese Klasse gehoert NICHT in eine Auslieferung. Sie wird nur aktiv, wenn
 * lastore.transport auf "static" steht.
 */
class StaticTransport implements Transport
{
    /** @var string */
    protected $dir;

    public function __construct($dir = null)
    {
        $this->dir = $dir === null ? __DIR__.'/../../Tests/Fixtures/api' : $dir;
    }

    public function get($path, array $query = [], array $headers = [])
    {
        return $this->resolve('GET', $path, $query);
    }

    public function post($path, array $body = [], array $headers = [])
    {
        return $this->resolve('POST', $path, $body);
    }

    protected function resolve($method, $path, array $data)
    {
        $path = trim($path, '/');

        // Die Aktivierung haengt am eingegebenen Schluessel - sonst koennte man
        // gegen den statischen Endpunkt nicht pruefen, ob der Client einen
        // falschen Schluessel ueberhaupt bemerkt.
        if ($method === 'POST' && $path === 'licenses/activate') {
            return $this->activate($data);
        }

        return $this->file($this->fileFor($method, $path));
    }

    protected function fileFor($method, $path)
    {
        if (preg_match('#^packages/([a-z0-9_-]+)/latest$#', $path, $m)) {
            return 'packages.'.$m[1].'.latest.json';
        }

        if (preg_match('#^catalog/([a-z0-9_-]+)$#', $path, $m)) {
            return 'catalog.'.$m[1].'.json';
        }

        return str_replace('/', '.', $path).'.json';
    }

    protected function activate(array $data)
    {
        $alias = isset($data['product_alias']) ? (string) $data['product_alias'] : '';
        $key = isset($data['license_key']) ? LicenseKey::normalize($data['license_key']) : '';

        $known = $this->file('_licenses.json');

        foreach ($known['licenses'] as $entry) {
            if (LicenseKey::normalize($entry['key']) !== $key) {
                continue;
            }

            if ($entry['product_alias'] !== $alias) {
                throw new StoreException(
                    __('Dieser Schlüssel gehört zu einem anderen Produkt.'),
                    'product_mismatch',
                    409
                );
            }

            return $entry['response'];
        }

        throw new StoreException(__('Dieser Lizenzschlüssel ist unbekannt.'), 'license_unknown', 404);
    }

    protected function file($name)
    {
        $path = $this->dir.'/'.$name;

        if (!is_file($path)) {
            throw new StoreException(
                __('Keine abgelegte Antwort für :file.', ['file' => $name]),
                'not_found',
                404
            );
        }

        $json = json_decode(file_get_contents($path), true);

        if (!is_array($json)) {
            throw new StoreException(__('Die abgelegte Antwort :file ist kein gültiges JSON.', ['file' => $name]), 'bad_response');
        }

        if (empty($json['ok'])) {
            $error = isset($json['error']) ? $json['error'] : [];

            throw new StoreException(
                isset($error['message']) ? $error['message'] : __('Unbekannter Fehler'),
                isset($error['code']) ? $error['code'] : 'unknown_error'
            );
        }

        return $json;
    }
}
