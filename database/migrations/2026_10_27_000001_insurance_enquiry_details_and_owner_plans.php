<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Insurance (owner, 27 Oct 2026): both forms also ask the age and the city / PIN code (with the state, from India Post's
 * list, like the Weekly News Paper's postal address); and the Life Insurance plans are the owner's four, in this order.
 */
return new class extends Migration
{
    /** The plans the first migration started with: replaced only while they are still exactly those */
    private const STARTER = ['Term Insurance', 'Savings & Guaranteed Income', 'ULIP (Market-Linked Plans)', 'Retirement & Pension', 'Child Plans'];

    public function up(): void
    {
        Schema::table('insurance_enquiries', function (Blueprint $table) {
            $table->unsignedTinyInteger('age')->nullable()->after('contact');
            $table->string('city', 60)->nullable()->after('address');
            $table->string('state', 60)->nullable()->after('city');
            $table->string('pincode', 6)->nullable()->after('state');
        });

        $life = DB::table('insurance_products')->where('insurance', 'life');
        if ($life->clone()->orderBy('sort_order')->pluck('name')->all() === self::STARTER) {
            $life->clone()->delete();
            $now = now();
            DB::table('insurance_products')->insert(array_map(fn ($p, $i) => [
                'insurance' => 'life', 'name' => $p[0], 'description' => $p[1], 'sort_order' => $i + 1, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ], $plans = [
                ['Term Plan', 'Pure protection: a large cover for your family at a low premium.'],
                ['Child Saving Plan', 'Save for your child\'s education and future milestones.'],
                ['Wealth Creation Plan', 'Grow your money for your goals, with life cover.'],
                ['Retirement Plans', 'Build a corpus and get a regular income after retirement.'],
            ], array_keys($plans)));
        }
    }

    public function down(): void
    {
        Schema::table('insurance_enquiries', function (Blueprint $table) {
            $table->dropColumn(['age', 'city', 'state', 'pincode']);
        });
    }
};
