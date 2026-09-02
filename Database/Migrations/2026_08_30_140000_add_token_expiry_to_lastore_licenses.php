<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddTokenExpiryToLastoreLicenses extends Migration
{
    public function up()
    {
        Schema::table('lastore_licenses', function (Blueprint $table) {
            // Das Ablaufdatum DIESES Tokens - kurz, weil sich der Client
            // täglich ein frisches holt. Getrennt von valid_until, das die
            // Vertragslaufzeit ist. Ohne diese Trennung zeigte die
            // Lizenzliste 45 Tage an, wo der Kunde ein Jahr gekauft hat.
            $table->dateTime('token_expires_at')->nullable()->after('token');
        });
    }

    public function down()
    {
        Schema::table('lastore_licenses', function (Blueprint $table) {
            $table->dropColumn('token_expires_at');
        });
    }
}
