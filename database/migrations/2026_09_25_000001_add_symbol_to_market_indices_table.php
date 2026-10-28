<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Yahoo Finance symbol (e.g. MRF.NS, ^NSEBANK) the live ticker fetches for this row.
     * Filled automatically from the name when the admin saves.
     */
    public function up()
    {
        if (Schema::hasColumn('market_indices', 'symbol')) {
            return; // some databases already have it
        }

        Schema::table('market_indices', function (Blueprint $table) {
            $table->string('symbol', 40)->nullable()->after('name');
        });
    }

    public function down()
    {
        Schema::table('market_indices', function (Blueprint $table) {
            $table->dropColumn('symbol');
        });
    }
};
