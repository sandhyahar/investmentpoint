<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When an AI-written article passed the independent fact-check against its source facts. */
return new class extends Migration
{
    public function up()
    {
        foreach (['latest_news', 'popular_news'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('fact_checked_at')->nullable()->after('source_url');
            });
        }
    }

    public function down()
    {
        foreach (['latest_news', 'popular_news'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('fact_checked_at'));
        }
    }
};
