<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Editor's Desk": Nikhil Bhatt's own daily articles, written in Admin -> Editor's Desk (menu after Blog). */
return new class extends Migration
{
    public function up()
    {
        Schema::create('editor_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('summary')->nullable();    // short intro on the list page
            $table->longText('content');            // the article (rich text)
            $table->string('image')->nullable();    // picture on the public disk (optional)
            $table->date('published_on');           // shown date + order, newest first
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['status', 'published_on']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('editor_articles');
    }
};
