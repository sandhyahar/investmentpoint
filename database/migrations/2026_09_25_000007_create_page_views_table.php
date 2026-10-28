<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per page a visitor opens on the public website (see App\Http\Middleware\TrackPageView). */
return new class extends Migration
{
    public function up()
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('route', 60);                     // e.g. news.show, home
            $table->string('subject_id', 60)->nullable();    // the article shown, e.g. news id 12
            $table->string('path', 255);
            $table->char('visitor', 16);                     // hashed random cookie id, no personal data
            $table->string('referrer_host', 120)->nullable();
            $table->string('device', 10);                    // mobile / desktop
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['route', 'subject_id']);
            $table->index(['visitor', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('page_views');
    }
};
