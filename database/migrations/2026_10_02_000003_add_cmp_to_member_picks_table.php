<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Member picks now show CMP (current market price), support and resistance.
     * "target" is no longer shown or entered; the column is kept so no old data is lost.
     */
    public function up()
    {
        Schema::table('member_picks', function (Blueprint $table) {
            if (!Schema::hasColumn('member_picks', 'cmp')) {
                $table->string('cmp', 60)->nullable()->after('symbol');
            }
        });
    }

    public function down()
    {
        Schema::table('member_picks', function (Blueprint $table) {
            $table->dropColumn('cmp');
        });
    }
};
