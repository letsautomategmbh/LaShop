# LaStore

Katalog, Lizenzierung und Installation der Module von let's automate — direkt
in FreeScout.

Umsetzung von Schritt **B** der Spezifikation *LaStore Protokoll v1*: der
Client steht vollständig, spricht aber noch gegen abgelegte JSON-Antworten
statt gegen einen Server.

## Betriebsart umstellen

In der `.env`:

    LASTORE_TRANSPORT=static   # abgelegte Antworten aus Tests/Fixtures/api
    LASTORE_TRANSPORT=http     # der echte Lizenzserver

Vorgabe ist `static`, solange es keinen Server gibt.

## Bestandskunden übernehmen

    php artisan lastore:adopt                       # Bestandsaufnahme
    php artisan lastore:adopt bexio --key=LA-…      # ein Modul übernehmen

Zeigt, was installiert ist, was davon eine Lizenz hat — und welche Module noch
Zugangsdaten in ihrer `module.json` tragen. Letzteres ist der Grund für die
ganze Übernahme: erst wenn alle Installationen umgestellt sind, darf das Token
aus den Repositories verschwinden.

Dasselbe steht auf der Store-Seite als Kachel „Bereits installiert, noch ohne
Lizenz".

Geht die lokale Lizenzzeile verloren — Rückspielen einer Sicherung,
Neuinstallation des Moduls — holt `lastore:sync` sie vom Server zurück, ohne
dass der Schlüssel noch einmal gebraucht wird. Der Sitz gehört weiterhin dieser
Installation.

## Abgleich von Hand

    php artisan lastore:sync
    php artisan lastore:sync --dry-run
    php artisan lastore:sync --force

Im Zeitplan läuft dasselbe Kommando täglich um 05:30.

## Fixtures neu erzeugen

    php Modules/LaStore/Tests/Fixtures/build.php

Braucht den privaten Entwicklungsschlüssel aus `Tests/Fixtures/keys` —
siehe die Warnung in der dortigen README.

## Lizenzschlüssel in Protokollen

Schlüssel werden als `SecretString` durch den Code gereicht, nicht als
Zeichenkette. PHP stellt in einem Stacktrace die tatsächlichen Argumente dar —
bei einer ungefangenen Ausnahme stand der vollständige Schlüssel sonst im
`laravel.log`, und das wird im Support herumgereicht. Objekte erscheinen dort
nur als Klassenname.

## Kryptographie

Tokens und Pakete werden mit **RSA-2048 / SHA-256** signiert und über
`openssl_verify()` geprüft, nicht mit Ed25519. Grund: `ext-sodium` fehlt in
der offiziellen FreeScout-Umgebung (geprüft: PHP 8.5, CLI und FPM), und
`paragonie/sodium_compat` liegt auch nicht im vendor-Baum. `openssl` ist
dagegen immer vorhanden, weil FreeScout es für TLS und IMAP braucht. Eine
Prüfung kostet gemessene 0,06 ms.

Das Modul kennt **zwei** öffentliche Schlüssel und akzeptiert beide. Ohne das
gäbe es später keinen Weg, den Signaturschlüssel zu wechseln, ohne jede
Installation gleichzeitig zu aktualisieren.

## Tests

    vendor/bin/phpunit Modules/LaStore/Tests/Unit
