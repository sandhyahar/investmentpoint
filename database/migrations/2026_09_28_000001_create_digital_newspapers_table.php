<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly "Digital News Paper" editions (menu: E-Paper -> Digital News Paper). Imported from
 * weeklynewspaper-ipoint.blogspot.com (php artisan newspaper:import) and managed in Admin.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('digital_newspapers', function (Blueprint $table) {
            $table->id();
            $table->string('blogger_id', 40)->nullable()->unique(); // set for imported posts, so re-imports skip them
            $table->string('title');
            $table->date('week_from')->nullable();   // "19.09.2026 TO 25.09.2026" in the title
            $table->date('week_to')->nullable();
            $table->date('published_on');            // sort / archive date: week start, else the post date
            $table->json('pages');                   // page images in order: Blogger URLs or paths on the public disk
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['status', 'published_on']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('digital_newspapers');
    }
};
