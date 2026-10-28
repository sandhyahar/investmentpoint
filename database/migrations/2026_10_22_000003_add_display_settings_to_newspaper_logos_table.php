<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Trusted by" managed by the admin: each newspaper row can now also have its own display name, a position,
 * be hidden from the home page, or be a paper added by hand (no Media Articles of its own); the logo is optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newspaper_logos', function (Blueprint $table) {
            $table->string('display_name', 120)->nullable()->after('name');
            $table->string('logo')->nullable()->change();
            $table->unsignedSmallInteger('sort_order')->nullable()->after('logo');
            $table->boolean('is_hidden')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('newspaper_logos', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'sort_order', 'is_hidden']);
        });
    }
};
