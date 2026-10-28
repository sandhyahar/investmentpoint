<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Digital News Letter" is now called "Daily News Letter" (plan title and its benefit line).
     * Only rows still using the old wording are touched, so admin edits are kept.
     */
    public function up()
    {
        $this->rename('Digital News Letter', 'Daily News Letter');
    }

    public function down()
    {
        $this->rename('Daily News Letter', 'Digital News Letter');
    }

    private function rename(string $from, string $to): void
    {
        DB::table('premium_contents')->where('title', $from)->update(['title' => $to]);

        foreach (DB::table('premium_contents')->get(['id', 'benefits']) as $row) {
            $benefits = json_decode((string) $row->benefits, true);
            if (!is_array($benefits)) {
                continue;
            }
            $renamed = array_map(fn ($b) => is_string($b) ? str_replace($from, $to, $b) : $b, $benefits);
            if ($renamed !== $benefits) {
                DB::table('premium_contents')->where('id', $row->id)->update(['benefits' => json_encode($renamed)]);
            }
        }
    }
};
