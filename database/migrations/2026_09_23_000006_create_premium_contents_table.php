<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('premium_contents', function (Blueprint $table) {
            $table->id();
            $table->string('badge')->default('INVESTMENTPOINT PREMIUM');
            $table->string('title');
            $table->text('description');
            $table->json('benefits');
            $table->string('button_text')->default('Subscribe Now');
            $table->string('button_url')->default('#');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('premium_contents');
    }
};
