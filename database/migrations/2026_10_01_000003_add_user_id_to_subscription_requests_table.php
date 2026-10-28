<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The member account a paid subscription belongs to (set when the reader creates an
 * account or logs in after paying). Gives access to the member dashboard.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('token')->constrained('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
