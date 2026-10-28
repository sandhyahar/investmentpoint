<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Trusted by": a logo may open its own link (e.g. a TV channel's YouTube playlist) instead of the paper's Media Articles */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newspaper_logos', function (Blueprint $table) {
            $table->string('link', 500)->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('newspaper_logos', function (Blueprint $table) {
            $table->dropColumn('link');
        });
    }
};
