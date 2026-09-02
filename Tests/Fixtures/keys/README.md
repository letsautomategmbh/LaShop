# Entwicklungsschlüssel — NICHT PRODUKTIV

`la1.key` und `la2.key` sind die privaten Schlüssel zu den öffentlichen
Schlüsseln in `Support/PublicKeys.php`. Sie liegen absichtlich hier, damit
Tests echte Signaturen erzeugen können statt gegen Attrappen zu prüfen, und
damit sich die Fixtures unter `Tests/Fixtures/api/` neu erzeugen lassen.

**Vor der ersten öffentlichen Auslieferung:**

1. Auf dem Lizenzserver ein neues Schlüsselpaar erzeugen.
2. Die öffentlichen Teile in `Support/PublicKeys.php` eintragen.
3. Den privaten Teil dort belassen und versiegelt im Tresor sichern —
   niemals in dieses Repository, niemals in GitHub Actions.
4. Für die Tests ein **eigenes**, drittes Paar erzeugen und in
   `Support/PublicKeys.php` NICHT eintragen; die Tests registrieren ihren
   Schlüssel dann selbst.

Solange Schritt 1 nicht passiert ist, könnte jeder mit Zugriff auf dieses
Repository gültige Lizenzen fälschen. Das ist für ein unveröffentlichtes
Modul in Ordnung und ab der ersten Auslieferung nicht mehr.
