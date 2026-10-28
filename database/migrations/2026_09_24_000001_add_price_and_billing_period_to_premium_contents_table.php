<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('premium_contents', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(100.00)->after('description');
            $table->string('billing_period')->default('MONTH')->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('premium_contents', function (Blueprint $table) {
            $table->dropColumn(['price', 'billing_period']);
        });
    }
};
