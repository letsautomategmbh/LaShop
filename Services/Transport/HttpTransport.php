<?php

namespace Modules\LaStore\Services\Transport;

use Modules\LaStore\Services\StoreException;

/**
 * Der echte Transport.
 *
 * Bei einem Transportfehler wird genau einmal die Ausweichadresse versucht -
 * dasselbe Muster wie WpApi::request() im Kern, das bei einer Exception
 * rekursiv auf freescout_alt_api wechselt. Ein Fehler DORT wird nicht noch
 * einmal umgeleitet, sonst dreht sich der Aufruf im Kreis.
 */
class HttpTransport implements Transport
{
    // Ueber die Einstellung ueberschreibbar, damit derselbe Stand gegen den
    // Entwicklungsserver und gegen die Produktivadresse laeuft.
    const BASE = 'https://shop.letsautomate.ch/api/store/v1';
    const FALLBACK = 'https://cdn.letsautomate.ch/api/store/v1';

    const CONNECT_TIMEOUT = 10;
    const TIMEOUT = 30;

    /** @var string */
    protected $clientVersion;

    public function __construct($clientVersion = '')
    {
        $this->clientVersion = (string) $clientVersion;
    }

    public function get($path, array $query = [], array $headers = [])
    {
        return $this->request('GET', $path, $query, $headers);
    }

    public function post($path, array $body = [], array $headers = [])
    {
        return $this->request('POST', $path, $body, $headers);
    }

    protected function request($method, $path, array $data, array $headers, $useFallback = false)
    {
        $base = $useFallback
            ? config('lastore.fallback_url', self::FALLBACK)
            : config('lastore.base_url', self::BASE);

        $url = rtrim($base, '/').'/'.ltrim($path, '/');

        $options = [
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout'         => self::TIMEOUT,
            'http_errors'     => false,
            'headers'         => array_merge([
                'Accept'          => 'application/json',
                'Accept-Language' => $this->locale(),
                // Damit der Server veraltete Clients erkennen und einen
                // Hinweis anhaengen kann, statt die Verbindung zu verweigern.
                'X-Store-Client'  => $this->clientVersion,
            ], $headers),
        ];

        if ($method === 'POST') {
            $options['json'] = $data;
        } else {
            $options['query'] = $data;
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request($method, $url, \Helper::setGuzzleDefaultOptions($options));
        } catch (\Exception $e) {
            if (!$useFallback) {
                return $this->request($method, $path, $data, $headers, true);
            }

            throw new StoreException($e->getMessage(), 'transport_error');
        }

        return $this->decode($response);
    }

    protected function decode($response)
    {
        $status = $response->getStatusCode();
        $json = json_decode((string) $response->getBody(), true);

        if (!is_array($json)) {
            throw new StoreException(__('Der Shop hat eine unlesbare Antwort geschickt.'), 'bad_response', $status);
        }

        if (empty($json['ok'])) {
            $error = isset($json['error']) && is_array($json['error']) ? $json['error'] : [];

            throw new StoreException(
                isset($error['message']) ? $error['message'] : __('Unbekannter Fehler'),
                isset($error['code']) ? $error['code'] : 'unknown_error',
                $status
            );
        }

        return $json;
    }

    protected function locale()
    {
        $locale = \App::getLocale();

        return $locale ? $locale : 'de';
    }
}
