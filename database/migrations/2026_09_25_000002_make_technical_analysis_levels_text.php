<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Support / resistance become short text so the admin can enter ranges like "610 - 620" or "618/620".
 * Existing numbers are kept (1390.00 -> "1390").
 * Uses Laravel's own column change (works on MySQL and on the SQLite test database).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('technical_analyses', function (Blueprint $table) {
            $table->string('support', 50)->nullable()->change();
            $table->string('resistance', 50)->nullable()->change();
        });

        // "1390.00" -> "1390", "616.50" -> "616.5"
        foreach (DB::table('technical_analyses')->get(['id', 'support', 'resistance']) as $row) {
            $clean = fn ($v) => ($v !== null && str_contains((string) $v, '.')) ? rtrim(rtrim((string) $v, '0'), '.') : $v;
            DB::table('technical_analyses')->where('id', $row->id)
                ->update(['support' => $clean($row->support), 'resistance' => $clean($row->resistance)]);
        }
    }

    public function down()
    {
        Schema::table('technical_analyses', function (Blueprint $table) {
            $table->decimal('support', 12, 2)->nullable()->change();
            $table->decimal('resistance', 12, 2)->nullable()->change();
        });
    }
};
