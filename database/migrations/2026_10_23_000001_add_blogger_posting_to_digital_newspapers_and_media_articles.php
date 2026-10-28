<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Paper editions and Media Articles uploaded in admin are posted to their Blogger blog (App\Services\BloggerPublisher):
 * blogger_url = the Blogger post (blogger_id holds its post id, so the hourly import skips it), blogger_error and
 * blogger_tries = why the last try failed and how often it was tried.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['digital_newspapers', 'media_articles'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('blogger_url', 500)->nullable()->after('blogger_id');
                $table->string('blogger_error', 500)->nullable()->after('blogger_url');
                $table->unsignedTinyInteger('blogger_tries')->default(0)->after('blogger_error');
            });
        }
    }

    public function down(): void
    {
        foreach (['digital_newspapers', 'media_articles'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['blogger_url', 'blogger_error', 'blogger_tries']);
            });
        }
    }
};
