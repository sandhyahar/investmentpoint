<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Services -> Insurance (header menu): the plan categories readers can tick on the Life / General Insurance page
 * (Admin -> Services -> Insurance Products) and what they send from it (Admin -> Services -> Insurance Enquiries):
 * kind "product" (interested in the ticked categories) or "adviser" (wants to become an adviser).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_products', function (Blueprint $table) {
            $table->id();
            $table->string('insurance', 20)->default('life'); // life | general
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('insurance_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('insurance', 20)->default('life');
            $table->string('kind', 20); // product | adviser
            $table->string('name', 100);
            $table->string('contact', 30);
            $table->string('address', 500);
            $table->json('products')->nullable(); // the ticked category names, kept even if a category is renamed or deleted later
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // The usual life insurance plan types, to start with (the admin can rename, reorder, hide or delete them)
        $now = now();
        DB::table('insurance_products')->insert(array_map(fn ($p, $i) => [
            'insurance' => 'life', 'name' => $p[0], 'description' => $p[1], 'sort_order' => $i + 1, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ], $plans = [
            ['Term Insurance', 'Pure protection: a large cover for your family at a low premium.'],
            ['Savings & Guaranteed Income', 'Save regularly and get assured returns for your goals.'],
            ['ULIP (Market-Linked Plans)', 'Insurance cover plus investment in market-linked funds.'],
            ['Retirement & Pension', 'Build a corpus and get a regular income after retirement.'],
            ['Child Plans', 'Secure your child\'s education and future milestones.'],
        ], array_keys($plans)));
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_enquiries');
        Schema::dropIfExists('insurance_products');
    }
};
