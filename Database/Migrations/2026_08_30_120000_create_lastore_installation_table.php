<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLastoreInstallationTable extends Migration
{
    public function up()
    {
        Schema::create('lastore_installation', function (Blueprint $table) {
            $table->increments('id');

            // Die Identitaet dieser FreeScout-Instanz gegenueber dem Shop.
            // Bewusst eine eigene UUID und NICHT die Domain: FreeScout selbst
            // bindet Lizenzen an parse_url(config('app.url'), PHP_URL_HOST),
            // was bei jedem Domainwechsel bricht und Test- von
            // Produktivkopien nicht unterscheiden kann.
            $table->string('installation_id', 64)->nullable();

            // Wird genau einmal ausgeliefert. Verschluesselt abgelegt, damit
            // ein Datenbankauszug allein nicht reicht, um Pakete zu ziehen.
            $table->text('secret')->nullable();

            $table->string('label')->nullable();
            $table->dateTime('registered_at')->nullable();
            $table->unsignedSmallInteger('heartbeat_hours')->default(24);
            $table->dateTime('last_heartbeat_at')->nullable();
            $table->dateTime('last_catalog_sync_at')->nullable();

            // Hoechste je gesehene Zeit. Ohne diesen Wert verlaengert ein
            // Zurueckstellen der Systemuhr jede Offline-Lizenz beliebig.
            $table->dateTime('max_seen_at')->nullable();

            // online | offline. Bei offline unterbleibt JEDER ausgehende
            // Aufruf - ein stiller Verbindungsversuch alle 24 Stunden taucht
            // in einem ueberwachten Netz sonst als Alarm auf.
            $table->string('mode', 16)->default('online');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lastore_installation');
    }
}
