<?php

return [
    'name' => 'LaStore',

    /*
     * "http" spricht mit dem echten Lizenzserver, "static" liest abgelegte
     * JSON-Antworten aus Tests/Fixtures/api.
     *
     * VORGABE IST http. Sie war static, solange es keinen Server gab -- und
     * diese Vorgabe hat am 02.09.2026 einen halben Tag gekostet: auf einer
     * Installation ohne LASTORE_TRANSPORT in der .env zeigte das Modul
     * erfundene Lizenzen, einen Katalog mit vier fremden Eintraegen, veraltete
     * Fassungen und sogar eine Installations-Kennung -- alles aus den
     * Testdateien, alles plausibel aussehend.
     *
     * Dazu kam die Falle, dass eine bestehende bootstrap/cache/config.php die
     * .env GAR NICHT liest: wer LASTORE_TRANSPORT=http eintrug, aenderte
     * nichts, solange der Cache stand.
     *
     * Eine falsche Vorgabe, die aussieht als funktioniere sie, ist schlimmer
     * als eine, die scheitert. Wer die Testdateien will, sagt es jetzt
     * ausdruecklich.
     */
    'transport' => env('LASTORE_TRANSPORT', 'http'),

    'fixtures' => env('LASTORE_FIXTURES', null),

    // Nur fuer Entwicklung und Abnahme umzustellen. In Produktion bleiben die
    // Vorgaben aus HttpTransport stehen.
    'base_url' => env('LASTORE_BASE_URL', \Modules\LaStore\Services\Transport\HttpTransport::BASE),

    /*
     * Wohin das Modul den Verwalter schickt.
     *
     * Kaufen, Lizenzen umziehen und die Offline-Lizenzdatei ERZEUGEN passiert
     * im Kundenportal, nicht hier - Entscheid vom 01.09.2026. Im Modul bleibt
     * nur, was das Portal nicht kann: die Datei auf DIESEN Server legen und
     * die Pruefung hier anstossen.
     */
    'portal_url' => env('LASTORE_PORTAL_URL', 'https://shop.letsautomate.ch/portal'),
    'shop_url'   => env('LASTORE_SHOP_URL', 'https://shop.letsautomate.ch/shop'),
    'fallback_url' => env('LASTORE_FALLBACK_URL', \Modules\LaStore\Services\Transport\HttpTransport::FALLBACK),

    'update_channel' => env('LASTORE_CHANNEL', 'stable'),

    /*
     * Zusaetzliche oeffentliche Schluessel aus einer JSON-Datei, NUR fuer die
     * Entwicklung. Ohne diesen Weg muessten die Entwicklungsschluessel in
     * PublicKeys::all() stehen - und waeren damit in jeder ausgelieferten
     * Fassung ein Generalschluessel. Vorgabe null: in der Produktion laedt
     * hier nie etwas, auch wenn die Datei versehentlich mitgeht.
     */
    'extra_public_keys' => env('LASTORE_EXTRA_PUBLIC_KEYS', null),
];
