<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Technical Analysis: the date shown on the cards and the article is chosen by the admin
     * (Admin -> Technical Analysis -> Date) instead of being the moment the row was created.
     * Existing analyses keep the date they show today.
     */
    public function up()
    {
        Schema::table('technical_analyses', function (Blueprint $table) {
            if (!Schema::hasColumn('technical_analyses', 'published_on')) {
                $table->date('published_on')->nullable()->after('status');
            }
        });

        foreach (DB::table('technical_analyses')->whereNull('published_on')->whereNotNull('created_at')->get(['id', 'created_at']) as $row) {
            DB::table('technical_analyses')->where('id', $row->id)
                ->update(['published_on' => Carbon::parse($row->created_at, 'UTC')->timezone('Asia/Kolkata')->format('Y-m-d')]);
        }
    }

    public function down()
    {
        Schema::table('technical_analyses', function (Blueprint $table) {
            $table->dropColumn('published_on');
        });
    }
};
