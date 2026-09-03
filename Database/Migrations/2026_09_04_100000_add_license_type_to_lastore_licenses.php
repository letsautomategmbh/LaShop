<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Die Kaufart der Lizenz -- 'one_time', 'monthly…', 'yearly…'.
 *
 * Warum das nachgetragen wird: die Modulkarte muss wissen, was das
 * Ablaufdatum bedeutet, und hat es bisher **geraten**. `ablaufDatum()`
 * schloss aus „ist valid_until gesetzt" auf ein Abonnement -- der Server
 * setzt aber bei einem Einmalkauf beide Datumsfelder. Folge: jede
 * Einmalkauf-Lizenz stand als „Nutzbar bis 30.08.2027" auf der Karte,
 * obwohl das Modul an dem Datum nicht aufhört zu laufen. Genau die
 * Verwechslung, die im Portal schon behoben war.
 *
 * Geraten werden musste nichts: das signierte Token trägt die Art seit
 * je als Anspruch `typ` (TokenIssuer.php). Der Client hat sie nur
 * verworfen.
 *
 * Nullbar, weil bestehende Zeilen sie erst beim naechsten taeglichen
 * Abgleich bekommen. Bis dahin greift in ablaufDatum() der alte Schluss --
 * lieber die bisherige Beschriftung als eine leere Zeile. */
class AddLicenseTypeToLastoreLicenses extends Migration
{
    public function up()
    {
        Schema::table('lastore_licenses', function (Blueprint $table) {
            $table->string('license_type', 30)->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('lastore_licenses', function (Blueprint $table) {
            $table->dropColumn('license_type');
        });
    }
}
