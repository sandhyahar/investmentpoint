<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('market_indices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('change_percent', 8, 2)->default(0);
            $table->enum('direction', ['up', 'down'])->default('up');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('market_indices');
    }
};
