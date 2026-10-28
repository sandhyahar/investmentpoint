<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Role & Permission Management: each role gets View / Create / Edit / Delete per page (one row per
 * permission, e.g. "news.create"), a description and an Active switch; admin-panel users get an
 * Active switch and "must change the password at the next login".
 * Roles made before this keep every action on the pages they had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->string('permission', 60); // App\Support\AdminPages: "page.action"
            $table->timestamps();
            $table->unique(['admin_role_id', 'permission']);
        });

        Schema::table('admin_roles', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('description');
        });

        foreach (DB::table('admin_roles')->get(['id', 'pages']) as $role) {
            $rows = [];
            foreach ((array) json_decode((string) $role->pages, true) as $page) {
                foreach (\App\Support\AdminPages::PAGES[$page][3] ?? [] as $action) {
                    $rows[] = ['admin_role_id' => $role->id, 'permission' => "{$page}.{$action}", 'created_at' => now(), 'updated_at' => now()];
                }
            }
            DB::table('admin_role_permissions')->insert($rows);
        }

        Schema::table('admin_roles', function (Blueprint $table) {
            $table->dropColumn('pages');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('admin_role_id');
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'must_change_password']);
        });
        Schema::table('admin_roles', function (Blueprint $table) {
            $table->json('pages')->nullable();
        });
        foreach (DB::table('admin_roles')->pluck('id') as $id) {
            $pages = DB::table('admin_role_permissions')->where('admin_role_id', $id)->pluck('permission')
                ->map(fn ($p) => strstr($p, '.', true))->unique()->values();
            DB::table('admin_roles')->where('id', $id)->update(['pages' => $pages->toJson()]);
        }
        Schema::table('admin_roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_active']);
        });
        Schema::dropIfExists('admin_role_permissions');
    }
};
