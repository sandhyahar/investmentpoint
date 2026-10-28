<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Media Articles can be scheduled too (like the E-Paper): readers and Blogger get one only from publish_at (UTC) on */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_articles', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('media_articles', function (Blueprint $table) {
            $table->dropIndex(['publish_at']);
            $table->dropColumn('publish_at');
        });
    }
};
