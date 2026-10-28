<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Member picks: a full description (rich text) that a member reads by clicking the stock's box. */
    public function up()
    {
        Schema::table('member_picks', function (Blueprint $table) {
            if (!Schema::hasColumn('member_picks', 'description')) {
                $table->longText('description')->nullable()->after('achieved');
            }
        });
    }

    public function down()
    {
        Schema::table('member_picks', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
