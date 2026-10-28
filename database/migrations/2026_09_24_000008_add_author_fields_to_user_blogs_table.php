<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blogs are now submitted without an author account: the writer's name and
     * optional photo are stored on the blog itself, and user_id becomes optional.
     */
    public function up()
    {
        Schema::table('user_blogs', function (Blueprint $table) {
            if (!Schema::hasColumn('user_blogs', 'author_name')) {
                $table->string('author_name', 100)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('user_blogs', 'author_photo')) {
                $table->string('author_photo')->nullable()->after('author_name');
            }
        });

        // Databases created before user_id became nullable in the create migration.
        // No doctrine/dbal in this project, so change the column with plain SQL (MySQL only).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE user_blogs MODIFY user_id BIGINT UNSIGNED NULL');
        }

        // Keep the names of blogs written by existing author accounts
        foreach (DB::table('user_blogs')->whereNull('author_name')->whereNotNull('user_id')->get(['id', 'user_id']) as $blog) {
            $name = DB::table('users')->where('id', $blog->user_id)->value('name');
            if ($name) {
                DB::table('user_blogs')->where('id', $blog->id)->update(['author_name' => $name]);
            }
        }
    }

    public function down()
    {
        Schema::table('user_blogs', function (Blueprint $table) {
            $table->dropColumn(['author_name', 'author_photo']);
        });
    }
};
