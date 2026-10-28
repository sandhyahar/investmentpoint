<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Weekly News Paper: an edition can be scheduled; readers see it only from publish_at (UTC) on */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_newspapers', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('digital_newspapers', function (Blueprint $table) {
            $table->dropIndex(['publish_at']);
            $table->dropColumn('publish_at');
        });
    }
};
