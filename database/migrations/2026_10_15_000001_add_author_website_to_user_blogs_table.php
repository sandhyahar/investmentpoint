<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Blog writer's own website (optional), shown as a link in the writer card on the blog */
    public function up()
    {
        Schema::table('user_blogs', function (Blueprint $table) {
            if (!Schema::hasColumn('user_blogs', 'author_website')) {
                $table->string('author_website', 255)->nullable()->after('author_reg_no');
            }
        });
    }

    public function down()
    {
        Schema::table('user_blogs', function (Blueprint $table) {
            $table->dropColumn('author_website');
        });
    }
};
