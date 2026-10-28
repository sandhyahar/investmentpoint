<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member area: the stock boxes shown at the top of the subscriber dashboard
 * (e.g. "Reliance Industries Ltd (RELIANCE)"), managed in Admin -> Member Stocks.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('member_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);                    // Reliance Industries Ltd
            $table->string('symbol', 30)->unique();         // RELIANCE
            $table->string('description', 255)->nullable(); // short line under the name in the box
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active'); // active | inactive
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('member_stocks');
    }
};
