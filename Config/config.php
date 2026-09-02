<?php

return [
    'name' => 'LaStore',

    /*
     * "static" liest abgelegte JSON-Antworten aus Tests/Fixtures/api,
     * "http" spricht mit dem echten Lizenzserver.
     *
     * Vorgabe ist static, solange es keinen Server gibt. Umstellbar ueber
     * LASTORE_TRANSPORT in der .env - ohne Codeaenderung, damit derselbe
     * Stand in beiden Betriebsarten laeuft.
     */
    'transport' => env('LASTORE_TRANSPORT', 'static'),

    'fixtures' => env('LASTORE_FIXTURES', null),

    // Nur fuer Entwicklung und Abnahme umzustellen. In Produktion bleiben die
    // Vorgaben aus HttpTransport stehen.
    'base_url' => env('LASTORE_BASE_URL', \Modules\LaStore\Services\Transport\HttpTransport::BASE),
    'fallback_url' => env('LASTORE_FALLBACK_URL', \Modules\LaStore\Services\Transport\HttpTransport::FALLBACK),

    'update_channel' => env('LASTORE_CHANNEL', 'stable'),
];
