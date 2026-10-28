<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Send this article" box on the article forms: news channels (their "submit news" link and / or email) and customer
 * emails that a Market Trend / Technical Analysis article can be sent to when it is saved,
 * plus a log of every send (sent / failed).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('press_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);                 // channel | customer
            $table->string('name', 150);
            $table->string('url', 500)->nullable();     // channel: link the article is posted to
            $table->string('email', 190)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['type', 'active']);
        });

        Schema::create('press_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('article_type', 20);         // news | analysis
            $table->unsignedBigInteger('article_id');
            $table->string('article_title', 255);
            $table->foreignId('press_contact_id')->nullable()->constrained('press_contacts')->nullOnDelete();
            $table->string('contact_name', 150);
            $table->string('via', 10);                  // link | email
            $table->string('target', 500);              // the link or email address
            $table->string('status', 10);               // sent | failed
            $table->string('message', 255)->nullable(); // why it failed
            $table->timestamp('created_at')->useCurrent();

            $table->index(['article_type', 'article_id']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('press_deliveries');
        Schema::dropIfExists('press_contacts');
    }
};
