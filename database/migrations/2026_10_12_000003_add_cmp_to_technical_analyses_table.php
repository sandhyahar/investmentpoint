<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Technical Analysis: CMP (current market price) next to support and resistance; a price or a range, like them. */
    public function up()
    {
        Schema::table('technical_analyses', function (Blueprint $table) {
            if (!Schema::hasColumn('technical_analyses', 'cmp')) {
                $table->string('cmp', 50)->nullable()->after('title');
            }
        });
    }

    public function down()
    {
        Schema::table('technical_analyses', function (Blueprint $table) {
            $table->dropColumn('cmp');
        });
    }
};
