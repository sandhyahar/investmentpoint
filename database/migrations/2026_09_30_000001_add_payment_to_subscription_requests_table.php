<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription payment (Razorpay), the paid period (from / to) and the WhatsApp message
 * sent to the reader after paying. Shown in Admin -> Subscriptions.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->string('token', 40)->nullable()->unique()->after('id');      // secret link to the payment / receipt page
            $table->string('payment_status', 20)->default('pending')->after('status'); // pending | paid
            $table->string('payment_mode', 10)->nullable()->after('payment_status');   // demo | test | live
            $table->string('razorpay_order_id', 60)->nullable()->after('payment_mode');
            $table->string('razorpay_payment_id', 60)->nullable()->after('razorpay_order_id');
            $table->string('razorpay_signature', 128)->nullable()->after('razorpay_payment_id');
            $table->timestamp('paid_at')->nullable()->after('razorpay_signature');
            $table->timestamp('starts_at')->nullable()->after('paid_at');
            $table->timestamp('expires_at')->nullable()->after('starts_at');
            $table->string('whatsapp_status', 20)->nullable()->after('expires_at'); // sent | failed | not_configured
            $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp_status');
            $table->string('whatsapp_error', 255)->nullable()->after('whatsapp_sent_at');

            $table->index(['phone', 'expires_at']);
        });
    }

    public function down()
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropIndex(['phone', 'expires_at']);
            $table->dropUnique(['token']);
            $table->dropColumn(['token', 'payment_status', 'payment_mode', 'razorpay_order_id', 'razorpay_payment_id',
                'razorpay_signature', 'paid_at', 'starts_at', 'expires_at', 'whatsapp_status', 'whatsapp_sent_at', 'whatsapp_error']);
        });
    }
};
