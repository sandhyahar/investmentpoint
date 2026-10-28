<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional writer details shown next to the writer name: designation
     * (e.g. "Research Analyst") and SEBI RA / IA registration number.
     */
    public function up()
    {
        Schema::table('user_blogs', function (Blueprint $table) {
            if (!Schema::hasColumn('user_blogs', 'author_designation')) {
                $table->string('author_designation', 100)->nullable()->after('author_photo');
            }
            if (!Schema::hasColumn('user_blogs', 'author_reg_no')) {
                $table->string('author_reg_no', 50)->nullable()->after('author_designation');
            }
        });
    }

    public function down()
    {
        Schema::table('user_blogs', function (Blueprint $table) {
            $table->dropColumn(['author_designation', 'author_reg_no']);
        });
    }
};
