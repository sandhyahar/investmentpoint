<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin -> News Inbox: headlines collected from publishers' public RSS feeds (news:collect).
 * They are never published as they are: the admin writes an original short article from one
 * (optionally with a free AI draft) into Business News / Most Popular, crediting the source.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('news_inbox_items', function (Blueprint $table) {
            $table->id();
            $table->string('source', 80);                // e.g. "Economic Times"
            $table->string('url', 1000);
            $table->char('url_hash', 40)->unique();      // sha1 of url: never collect a story twice
            $table->string('title', 500);
            $table->text('summary')->nullable();         // the feed's short description
            $table->dateTime('published_at');
            $table->unsignedSmallInteger('score')->default(0);    // business keyword matches
            $table->unsignedSmallInteger('coverage')->default(1); // how many publishers carry the story
            $table->string('status', 20)->default('new');         // new | used | dismissed
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        foreach (['latest_news', 'popular_news'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('source_url', 1000)->nullable()->after('source'); // credit link to the original report
            });
        }
    }

    public function down()
    {
        foreach (['latest_news', 'popular_news'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('source_url'));
        }
        Schema::dropIfExists('news_inbox_items');
    }
};
