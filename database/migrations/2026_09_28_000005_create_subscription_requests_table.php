<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription page: every "Subscription" request with the reader's agreement to the
 * Terms & Conditions, Privacy Policy, Refund Policy and Disclaimer (proof of consent).
 * Listed in Admin -> Subscriptions.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('subscription_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->string('plan', 60);                  // e.g. "Monthly Plan"
            $table->unsignedInteger('price');            // rupees, as shown when they agreed
            $table->timestamp('agreed_at');              // when the "I agree" box was ticked and sent
            $table->string('agreed_to', 255);            // which documents they agreed to
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('status', 20)->default('new'); // new | contacted | cancelled
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscription_requests');
    }
};
