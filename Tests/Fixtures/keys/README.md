# Fixture-Schlüssel — reines Testmaterial

`la1.key` und `la2.key` liegen absichtlich hier und sind absichtlich
versioniert (`.gitignore` nimmt sie ausdrücklich aus `*.key` heraus). Sie
zeichnen die abgelegten Antworten unter `Tests/Fixtures/api`, damit der Client
echte Signaturen prüft statt Attrappen.

**Das ist gefahrlos, weil sie in `Support/PublicKeys.php` nicht stehen.** Die
ausgelieferte Schlüsselliste kennt nur die Produktionsschlüssel `lat1`/`lat2`
(token) und `lap1`/`lap2` (package). Ein Token, das mit `la1` gezeichnet ist,
fällt in jeder ausgelieferten Installation als `unknown_key` durch — gemessen.

Zwei Stellen kennen sie trotzdem:

1. **Die Unittests** geben sie über `TokenFactory::publicKeys()` hinein.
   Abgeleitet aus den privaten Schlüsseln, nicht als zweite Datei daneben, die
   veralten könnte.
2. **Die Entwicklungsumgebung** lädt sie über `LASTORE_EXTRA_PUBLIC_KEYS` aus
   `/data/config/lastore-entwicklungsschluessel.json`, damit `StaticTransport`
   und `Tests/Manual/smoke.php` gegen die abgelegten Antworten laufen können.
   Vorgabe der Einstellung ist `null` — in der Produktion lädt dort nie etwas.

## Was hier früher stand

Diese Datei beschrieb einen Plan: „vor der ersten öffentlichen Auslieferung
Produktionsschlüssel erzeugen, für die Tests ein eigenes drittes Paar". Der
Plan ist am 01.09.2026 umgesetzt — mit dem Unterschied, dass die Tests kein
drittes Paar brauchten, weil `la1`/`la2` selbst zum Testmaterial geworden sind,
sobald sie aus der ausgelieferten Liste verschwanden.

Der **Entwicklungsserver** zeichnete bis dahin mit genau diesem Paar. Damit
hätte ein Commit hier seinen Signaturschlüssel veröffentlicht. Er hat jetzt sein
eigenes (`dev1`/`dev2` in `LaStore-Server/secrets/`, nicht versioniert).

## Wenn diese Schlüssel doch abfliessen

Nichts zu tun. Sie geben Zugriff auf nichts: keine ausgelieferte Installation
vertraut ihnen, kein Server zeichnet mit ihnen. Wer sie hat, kann sich
Fixtures bauen — die kann er sich auch ohne sie bauen.
