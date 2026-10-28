<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('news', 'category')) {
            Schema::table('news', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('news', 'category')) {
            Schema::table('news', function (Blueprint $table) {
                $table->string('category')->default('Stock Market Trend')->after('title');
            });
        }
    }
};
