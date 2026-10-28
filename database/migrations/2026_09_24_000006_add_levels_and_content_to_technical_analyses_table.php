<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('technical_analyses', function (Blueprint $table) {
            if (!Schema::hasColumn('technical_analyses', 'support')) {
                $table->decimal('support', 12, 2)->nullable()->after('title');
            }
            if (!Schema::hasColumn('technical_analyses', 'resistance')) {
                $table->decimal('resistance', 12, 2)->nullable()->after('support');
            }
            if (!Schema::hasColumn('technical_analyses', 'content')) {
                $table->longText('content')->nullable()->after('description');
            }
        });

        // Levels for the default demo analyses (admin can change them)
        $levels = [
            'SBI Cards: Support, Resistance & Key Levels' => [616, 660],
            'Reliance Industries: Key Chart Levels' => [1390, 1480],
            'TCS: Trend, Momentum & Levels' => [3380, 3560],
            'Infosys: Trend, Support & Resistance' => [1480, 1560],
        ];

        foreach ($levels as $title => [$support, $resistance]) {
            DB::table('technical_analyses')
                ->where('title', $title)
                ->whereNull('support')
                ->update(['support' => $support, 'resistance' => $resistance]);
        }
    }

    public function down()
    {
        Schema::table('technical_analyses', function (Blueprint $table) {
            $table->dropColumn(['support', 'resistance', 'content']);
        });
    }
};
