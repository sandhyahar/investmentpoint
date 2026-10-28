<?php

namespace Tests\Feature;

use App\Models\MarketIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminMarketIndexTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator',
            'username' => 'admin-test',
            'email' => 'admin-test@example.com',
            'role' => 'admin',
            'password' => bcrypt('secret'),
        ]);
    }

    private function fakeYahoo(): void
    {
        Http::fake([
            'query1.finance.yahoo.com/v1/finance/search*' => Http::response(['quotes' => [
                ['symbol' => 'MRF.BO', 'exchange' => 'BSE', 'quoteType' => 'EQUITY'],
                ['symbol' => 'MRF.NS', 'exchange' => 'NSI', 'quoteType' => 'EQUITY'],
            ]]),
            'query1.finance.yahoo.com/v7/finance/spark*' => Http::response(['spark' => ['result' => [
                ['symbol' => 'MRF.NS', 'response' => [['meta' => ['regularMarketPrice' => 123815, 'chartPreviousClose' => 124000, 'regularMarketTime' => time()]]]],
            ]]]),
            '*' => Http::response('', 404),
        ]);
    }

    public function test_renaming_a_ticker_row_looks_up_the_new_symbol_and_live_price(): void
    {
        $this->fakeYahoo();
        $row = MarketIndex::create(['name' => 'NIFTY 50', 'symbol' => '^NSEI', 'value' => 23000, 'change_percent' => 0.1, 'direction' => 'up']);

        $this->actingAs($this->admin())
            ->put(route('admin.market-indices.update', $row), ['name' => 'MRF', 'symbol' => '^NSEI'])
            ->assertRedirect(route('admin.market-indices.index'));

        $row->refresh();
        $this->assertSame('MRF', $row->name);
        $this->assertSame('MRF.NS', $row->symbol); // NSE listing preferred over BSE
        $this->assertEquals(123815, $row->value);
        $this->assertSame('down', $row->direction);
    }

    public function test_unknown_name_shows_an_error_instead_of_saving(): void
    {
        Http::fake(['*' => Http::response(['quotes' => []])]);
        $row = MarketIndex::create(['name' => 'NIFTY 50', 'symbol' => '^NSEI']);

        $this->actingAs($this->admin())
            ->put(route('admin.market-indices.update', $row), ['name' => 'Not A Real Company', 'symbol' => '^NSEI'])
            ->assertSessionHasErrors('symbol');

        $this->assertSame('NIFTY 50', $row->fresh()->name);
    }

    public function test_ticker_bar_shows_the_first_rows_by_id(): void
    {
        // The ticker shows the first config('market.ticker_count') rows (4: e.g. SENSEX ... GOLD)
        foreach (['MRF', 'SENSEX', 'BANK NIFTY', 'GOLD', 'Hidden Row'] as $name) {
            MarketIndex::create(['name' => $name]);
        }

        $html = $this->get('/subscription')->assertOk()->getContent();
        $ticker = substr($html, strpos($html, '<div class="ticker">'), 4000);

        $this->assertStringContainsString('<small>MRF</small>', $ticker);
        $this->assertStringContainsString('<small>GOLD</small>', $ticker);
        $this->assertStringNotContainsString('Hidden Row', $ticker);
        $this->assertSame(4, substr_count($ticker, 'class="quote"'));
        $this->assertStringContainsString('grid-template-columns:repeat(4,', $html);
    }
}
