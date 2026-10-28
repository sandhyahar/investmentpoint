<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newspaper clippings for Media -> Media Article. Imported from mediaarticle-ipoint.blogspot.com
 * (php artisan media:import) and managed in Admin -> Media Articles.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('media_articles', function (Blueprint $table) {
            $table->id();
            $table->string('blogger_id', 40)->nullable()->unique(); // set for imported posts, so re-imports skip them
            $table->string('title');
            $table->string('column_name', 120)->nullable();  // e.g. INVESTMENT POINT, MARKET SCAN
            $table->string('publication', 120)->nullable();  // newspaper, e.g. PAGDANDI
            $table->date('published_on')->nullable();
            $table->string('image', 1000)->nullable();       // Blogger image URL, or a path on the public disk
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['status', 'published_on']);
            $table->index('publication');
        });
    }

    public function down()
    {
        Schema::dropIfExists('media_articles');
    }
};
