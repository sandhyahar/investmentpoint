<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homepage "Latest News" box and the "Most Popular" sidebar list, each managed in its own
 * admin section (Admin -> Latest News / Most Popular News) instead of the live RSS feeds.
 */
return new class extends Migration
{
    public function up()
    {
        foreach (['latest_news', 'popular_news'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description');                     // short summary shown in lists
                $table->longText('content')->nullable();         // full article; falls back to the summary
                $table->string('image', 1000)->nullable();       // uploaded path on the public disk, or an image link
                $table->string('source', 120)->nullable();       // e.g. "Economic Times" (optional credit line)
                $table->dateTime('published_at')->nullable();
                $table->unsignedInteger('sort_order')->default(0); // Most Popular: 1 = top of the list
                $table->unsignedInteger('views')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['status', 'published_at']);
                $table->index(['status', 'sort_order']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('popular_news');
        Schema::dropIfExists('latest_news');
    }
};
