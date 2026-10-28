<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Articles by clients / guest experts for the Expert's View page. Imported from
 * expertview-ipoint.blogspot.com (php artisan experts:import) and managed in Admin -> Expert's View.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('expert_posts', function (Blueprint $table) {
            $table->id();
            $table->string('blogger_id', 40)->nullable()->unique();
            $table->string('title', 500);
            $table->string('author', 150)->nullable();   // e.g. Srishti Mendiratta; empty = Investment Point
            $table->longText('content');                  // cleaned HTML
            $table->string('image', 1000)->nullable();    // cover: first picture in the post, or an upload
            $table->date('published_on');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['status', 'published_on']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('expert_posts');
    }
};
