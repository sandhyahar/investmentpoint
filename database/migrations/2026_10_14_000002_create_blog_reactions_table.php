<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Like / dislike buttons on blog pages: one vote per reader (anonymous visitor id, see App\Support\Visitor)
     * per blog, which they can change or take back.
     */
    public function up()
    {
        if (Schema::hasTable('blog_reactions')) {
            return;
        }
        Schema::create('blog_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_blog_id')->constrained('user_blogs')->cascadeOnDelete();
            $table->string('visitor', 16);
            $table->tinyInteger('reaction'); // 1 = like, -1 = dislike
            $table->timestamps();
            $table->unique(['user_blog_id', 'visitor']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('blog_reactions');
    }
};
