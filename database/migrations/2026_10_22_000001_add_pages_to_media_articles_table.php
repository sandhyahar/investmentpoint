<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Media articles uploaded as a PDF: one image per page (in order); "image" stays the first page (the cover) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_articles', function (Blueprint $table) {
            $table->json('pages')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('media_articles', function (Blueprint $table) {
            $table->dropColumn('pages');
        });
    }
};
