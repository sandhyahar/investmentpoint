<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Questions sent from the "Ask anything" box on the Disclaimer page, read in Admin -> Reader Queries. */
return new class extends Migration
{
    public function up()
    {
        Schema::create('reader_queries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('contact', 150);
            $table->text('message');
            $table->json('attachments')->nullable(); // [{path, name, size}] on the private "local" disk
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reader_queries');
    }
};
