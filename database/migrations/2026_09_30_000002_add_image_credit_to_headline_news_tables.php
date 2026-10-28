<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Photographer credit for real stock photos (Pexels asks for "Photo by <name> on Pexels" when possible). */
return new class extends Migration
{
    public function up()
    {
        foreach (['latest_news', 'popular_news'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('image_credit', 150)->nullable()->after('image');
                $table->string('image_credit_url', 500)->nullable()->after('image_credit');
            });
        }
    }

    public function down()
    {
        foreach (['latest_news', 'popular_news'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['image_credit', 'image_credit_url']));
        }
    }
};
