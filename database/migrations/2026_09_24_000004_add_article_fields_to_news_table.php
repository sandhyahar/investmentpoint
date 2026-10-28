<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('news', function (Blueprint $table) {
            // (A "category" column was added here earlier and removed in 000005; fresh installs skip it)
            if (!Schema::hasColumn('news', 'content')) {
                $table->longText('content')->nullable()->after('description');
            }
            if (!Schema::hasColumn('news', 'views')) {
                $table->unsignedInteger('views')->default(0)->after('is_featured');
            }
        });
    }

    public function down()
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['content', 'views']);
        });
    }
};
