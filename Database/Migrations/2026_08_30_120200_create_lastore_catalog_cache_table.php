<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLastoreCatalogCacheTable extends Migration
{
    public function up()
    {
        Schema::create('lastore_catalog_cache', function (Blueprint $table) {
            $table->increments('id');
            $table->string('alias', 64)->unique();

            // Der Katalogeintrag, so wie der Server ihn geliefert hat.
            // Als eigene Tabelle und nicht im Cache, damit die Store-Seite
            // auch dann etwas zeigt, wenn der Server nicht erreichbar ist -
            // ein leerer Katalog sieht aus wie ein Fehler.
            $table->text('payload')->nullable();
            $table->dateTime('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lastore_catalog_cache');
    }
}
