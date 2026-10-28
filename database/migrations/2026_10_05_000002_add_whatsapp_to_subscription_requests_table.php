<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The subscriber's WhatsApp number ("+91XXXXXXXXXX"): login OTPs and the payment confirmation go there.
     * Empty = same as the mobile number (and for subscriptions made before this column existed).
     */
    public function up()
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_requests', 'whatsapp')) {
                $table->string('whatsapp', 16)->nullable()->after('phone')->index();
            }
        });
    }

    public function down()
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropColumn('whatsapp');
        });
    }
};
