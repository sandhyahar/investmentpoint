<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disclaimer, Privacy Policy, Terms & Conditions and Refund Policy, edited in Admin -> Legal Pages.
     * No row yet = the page shows its built-in default text (resources/views/legal/defaults).
     */
    public function up()
    {
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('title');
            $table->string('subtitle', 500)->nullable();
            $table->text('meta')->nullable();
            $table->longText('content');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('legal_pages');
    }
};
