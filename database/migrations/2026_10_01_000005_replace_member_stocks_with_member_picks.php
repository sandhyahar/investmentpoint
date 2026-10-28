<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member page: the fixed stock boxes + date-wise open/high/low/close table are replaced by
 * daily stock picks: for each date the admin adds a few stocks with support, resistance and target.
 * (The old tables only held demo data.)
 */
return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('member_stock_prices');
        Schema::dropIfExists('member_stocks');

        Schema::create('member_picks', function (Blueprint $table) {
            $table->id();
            $table->date('pick_date');
            $table->string('stock_name', 120);           // Reliance Industries Ltd
            $table->string('symbol', 30)->nullable();     // RELIANCE
            $table->string('support', 60)->nullable();    // "2755" or a range "2750-2760"
            $table->string('resistance', 60)->nullable();
            $table->string('target', 60)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pick_date', 'sort_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('member_picks');

        Schema::create('member_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('symbol', 30)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
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
        });
    }
};
