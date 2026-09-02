<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLastoreLicensesTable extends Migration
{
    public function up()
    {
        Schema::create('lastore_licenses', function (Blueprint $table) {
            $table->increments('id');

            // Der Modul-Alias, klein geschrieben - so wie FreeScout ihn in
            // module.json fuehrt und Module::getAlias() ihn zurueckgibt.
            $table->string('product_alias', 64)->unique();

            $table->text('license_key')->nullable();
            $table->text('token')->nullable();

            $table->string('status', 32)->default('unknown');
            $table->unsignedInteger('seats')->nullable();

            // Spiegelwerte aus dem Token, nur fuer Liste und Filter.
            // Massgeblich ist immer das Token selbst - diese Spalten sind
            // bequem, aber nie die Grundlage einer Entscheidung.
            $table->dateTime('valid_until')->nullable();
            $table->dateTime('grace_until')->nullable();
            $table->dateTime('updates_until')->nullable();

            $table->dateTime('checked_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lastore_licenses');
    }
}
