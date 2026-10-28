<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nikhil Bhatt's columns on divyabhaskar.com (Media -> Divya Bhaskar). Imported from
 * db-ipoint.blogspot.com (php artisan divya:import) and managed in Admin -> Divya Bhaskar.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('divya_bhaskar_articles', function (Blueprint $table) {
            $table->id();
            $table->string('blogger_id', 40)->nullable()->unique();
            $table->string('headline', 500);
            $table->string('link', 500);            // the column on divyabhaskar.com
            $table->date('published_on');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['status', 'published_on']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('divya_bhaskar_articles');
    }
};
