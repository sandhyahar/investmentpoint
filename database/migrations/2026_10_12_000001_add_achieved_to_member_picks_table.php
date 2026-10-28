<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Member picks: "Achieved" (the level a stock reached) is entered next to CMP, support and resistance.
     * "symbol" is no longer shown or entered; the column is kept so no old data is lost.
     */
    public function up()
    {
        Schema::table('member_picks', function (Blueprint $table) {
            if (!Schema::hasColumn('member_picks', 'achieved')) {
                $table->string('achieved', 60)->nullable()->after('resistance');
            }
        });

        // Until now the achieved level was typed into "Symbol" (e.g. 2300): carry those numbers over.
        // Real symbols (RELIANCE, HDFCBANK) are left where they are.
        foreach (DB::table('member_picks')->whereNotNull('symbol')->whereNull('achieved')->get(['id', 'symbol']) as $pick) {
            if (preg_match('/^\s*₹?\s*\d/u', $pick->symbol)) {
                DB::table('member_picks')->where('id', $pick->id)->update(['achieved' => trim($pick->symbol)]);
            }
        }
    }

    public function down()
    {
        Schema::table('member_picks', function (Blueprint $table) {
            $table->dropColumn('achieved');
        });
    }
};
