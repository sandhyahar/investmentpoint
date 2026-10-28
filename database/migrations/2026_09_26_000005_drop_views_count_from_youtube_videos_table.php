<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** YouTube view counts were typed in by hand, not real numbers: no longer stored or shown. */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('youtube_videos', 'views_count')) {
            Schema::table('youtube_videos', function (Blueprint $table) {
                $table->dropColumn('views_count');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('youtube_videos', 'views_count')) {
            Schema::table('youtube_videos', function (Blueprint $table) {
                $table->string('views_count')->default('0');
            });
        }
    }
};
