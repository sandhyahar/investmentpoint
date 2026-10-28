<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Readers' ❤ likes on articles: one row per visitor per article. */
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);     // news, analysis, expert, media, blog, live
            $table->string('subject', 64);  // article id (live headline: sha1 of its link)
            $table->char('visitor', 16);    // anonymous visitor id, same as page_views.visitor
            $table->timestamp('created_at')->nullable();
            $table->unique(['type', 'subject', 'visitor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
