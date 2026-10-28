<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('youtube_videos')) {
            Schema::table('youtube_videos', function (Blueprint $table) {
                if (!Schema::hasColumn('youtube_videos', 'video_thumbnail')) {
                    $table->string('video_thumbnail')->nullable();
                }
                if (!Schema::hasColumn('youtube_videos', 'video_url')) {
                    $table->string('video_url')->nullable();
                }
                if (!Schema::hasColumn('youtube_videos', 'published_date')) {
                    $table->date('published_date')->nullable();
                }
            });
        }

        if (Schema::hasTable('experts')) {
            Schema::table('experts', function (Blueprint $table) {
                if (!Schema::hasColumn('experts', 'role')) {
                    $table->string('role')->nullable();
                }
                if (!Schema::hasColumn('experts', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('experts', 'image')) {
                    $table->string('image')->nullable();
                }
                if (!Schema::hasColumn('experts', 'status')) {
                    $table->string('status')->default('active');
                }
            });
        }
    }

    public function down()
    {
        // Legacy columns are intentionally retained when rolling back.
    }
};
