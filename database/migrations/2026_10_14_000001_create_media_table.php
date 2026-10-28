<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Media Library (Admin -> Media Library): pictures uploaded once and then picked for any image
     * field in the admin panel, like the WordPress media library.
     */
    public function up()
    {
        if (Schema::hasTable('media')) {
            return;
        }
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('name');
            $table->string('mime', 50)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('media');
    }
};
