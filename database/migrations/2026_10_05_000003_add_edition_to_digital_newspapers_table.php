<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One table for every E-Paper: the Weekly News Paper and the daily Gujarati / Hindi / English editions
 * (managed in Admin -> E-Paper, imported from their blogspots by `php artisan newspaper:import --edition=...`).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('digital_newspapers', function (Blueprint $table) {
            if (!Schema::hasColumn('digital_newspapers', 'edition')) {
                $table->string('edition', 20)->default('weekly')->after('id'); // weekly | gujarati | hindi | english
                $table->index(['edition', 'status', 'published_on']);
            }
        });
    }

    public function down()
    {
        Schema::table('digital_newspapers', function (Blueprint $table) {
            $table->dropIndex(['edition', 'status', 'published_on']);
            $table->dropColumn('edition');
        });
    }
};
