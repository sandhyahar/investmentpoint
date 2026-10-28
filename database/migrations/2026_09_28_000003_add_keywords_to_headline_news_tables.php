<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SEO keywords for Business News / Most Popular articles (written by the AI, editable in admin). */
return new class extends Migration
{
    public function up()
    {
        foreach (['latest_news', 'popular_news'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('keywords', 500)->nullable()->after('description');
            });
        }
    }

    public function down()
    {
        foreach (['latest_news', 'popular_news'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('keywords'));
        }
    }
};
