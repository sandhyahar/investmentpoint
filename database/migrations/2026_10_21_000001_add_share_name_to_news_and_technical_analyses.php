<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Market Trend / Technical Analysis: the admin's own short share link (investmentpoint.in/{share_name}).
     * Empty = the name made from the title, as before.
     */
    public function up()
    {
        foreach (['news', 'technical_analyses'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (!Schema::hasColumn($name, 'share_name')) {
                    $table->string('share_name', 60)->nullable()->after('title');
                }
            });
        }
    }

    public function down()
    {
        foreach (['news', 'technical_analyses'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('share_name');
            });
        }
    }
};
