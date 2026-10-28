<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Clicks on live headlines (Economic Times / LiveMint) in the homepage "Latest News" box. */
    public function up(): void
    {
        Schema::create('news_clicks', function (Blueprint $table) {
            $table->id();
            $table->char('url_hash', 40)->unique();
            $table->text('url');
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_clicks');
    }
};
