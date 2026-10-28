<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two plans on the subscription page (config/subscription.php 'plans'): which one was bought, how many
     * months it lasts, and the postal address for the printed paper. Older rows (the Premium plan) keep
     * plan_key empty and still give member access.
     */
    public function up()
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_requests', 'plan_key')) {
                $table->string('plan_key', 20)->nullable()->after('plan')->index();
                $table->unsignedTinyInteger('months')->nullable()->after('price');
                $table->text('address')->nullable()->after('email');
            }
        });
    }

    public function down()
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropIndex(['plan_key']);
            $table->dropColumn(['plan_key', 'months', 'address']);
        });
    }
};
