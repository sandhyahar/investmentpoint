<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Official logo of each newspaper (Admin -> Media Articles -> Newspaper Logos), shown in "Trusted by" */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newspaper_logos', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique(); // MediaArticle::normalizePublication()
            $table->string('logo');                // path on the public disk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newspaper_logos');
    }
};
