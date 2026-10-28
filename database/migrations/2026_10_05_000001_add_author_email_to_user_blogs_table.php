<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Writer's email from the Create Blog form (required there, never shown on the website).
     * Nullable only because blogs sent before this change have none.
     */
    public function up()
    {
        Schema::table('user_blogs', function (Blueprint $table) {
            if (!Schema::hasColumn('user_blogs', 'author_email')) {
                $table->string('author_email', 150)->nullable()->after('author_name');
            }
        });
    }

    public function down()
    {
        Schema::table('user_blogs', function (Blueprint $table) {
            $table->dropColumn('author_email');
        });
    }
};
