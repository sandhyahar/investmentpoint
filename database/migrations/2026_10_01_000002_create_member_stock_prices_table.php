<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member area: date-wise prices of a member stock (one row per stock per day),
 * entered in Admin -> Stock Prices and shown when a subscriber clicks the stock's box.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('member_stock_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_stock_id')->constrained('member_stocks')->cascadeOnDelete();
            $table->date('price_date');
            $table->decimal('open', 12, 2)->nullable();
            $table->decimal('high', 12, 2)->nullable();
            $table->decimal('low', 12, 2)->nullable();
            $table->decimal('close', 12, 2);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['member_stock_id', 'price_date']);
            $table->index('price_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('member_stock_prices');
    }
};
