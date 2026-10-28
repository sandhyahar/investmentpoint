<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MarketIndex;
use App\Models\News;
use App\Models\YoutubeVideo;
use App\Models\PremiumContent;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Create default admin user
        $this->call(AdminUserSeeder::class);

        // Seed default market indices
        MarketIndex::firstOrCreate(['name' => 'NIFTY 50'], [
            'name' => 'NIFTY 50',
            'value' => 25562.40,
            'change_percent' => 0.82,
            'direction' => 'up',
        ]);

        MarketIndex::firstOrCreate(['name' => 'SENSEX'], [
            'name' => 'SENSEX',
            'value' => 81216.24,
            'change_percent' => 0.74,
            'direction' => 'up',
        ]);

        MarketIndex::firstOrCreate(['name' => 'BANK NIFTY'], [
            'name' => 'BANK NIFTY',
            'value' => 55243.10,
            'change_percent' => 0.21,
            'direction' => 'down',
        ]);

        MarketIndex::firstOrCreate(['name' => 'INDIA VIX'], [
            'name' => 'INDIA VIX',
            'value' => 14.32,
            'change_percent' => 1.42,
            'direction' => 'up',
        ]);

        foreach ([
            ['name' => 'Tata Motors', 'value' => 982.40, 'change_percent' => 4.82, 'direction' => 'up'],
            ['name' => 'Reliance Ind.', 'value' => 2945.20, 'change_percent' => 3.91, 'direction' => 'up'],
            ['name' => 'HDFC Bank', 'value' => 1762.10, 'change_percent' => 3.25, 'direction' => 'up'],
            ['name' => 'Infosys', 'value' => 1540.80, 'change_percent' => 2.98, 'direction' => 'up'],
            ['name' => 'ICICI Bank', 'value' => 1324.50, 'change_percent' => 2.76, 'direction' => 'up'],
            ['name' => 'Bharti Airtel', 'value' => 1642.30, 'change_percent' => 3.42, 'direction' => 'down'],
            ['name' => 'Sun Pharma', 'value' => 1685.90, 'change_percent' => 2.84, 'direction' => 'down'],
            ['name' => 'TCS', 'value' => 3380.00, 'change_percent' => 2.31, 'direction' => 'down'],
            ['name' => 'Wipro', 'value' => 512.40, 'change_percent' => 1.98, 'direction' => 'down'],
            ['name' => 'ITC', 'value' => 458.15, 'change_percent' => 1.76, 'direction' => 'down'],
        ] as $index) {
            MarketIndex::firstOrCreate(['name' => $index['name']], $index);
        }

        foreach ([
            ['title' => 'Sensex Rallies as Banking & IT Stocks Lead Market Gains', 'description' => 'Banking and information technology stocks led broad market gains as investors responded to improving earnings expectations and steady domestic flows.', 'image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80'],
            ['title' => 'Bank Credit Growth Reaches 19% as Deposit Growth Rises to 18%', 'description' => 'Banking sector credit growth has accelerated to 19%, while deposit growth has also increased to 18%, according to the latest report.', 'image' => 'https://investmentpoint.in/wp-content/uploads/2026/02/bull-running-stock-market-chart-financial-concept-illustration-bull-running-stock-market-chart-representing-growth-378572061-150x150.png'],
            ['title' => 'Mills Sold Only 7 Lakh Tonnes of Sugar at ₹50 per Kg in September', 'description' => 'Sugar mills sold around 7 lakh tonnes at about ₹50 per kg in September, while the average price during the season was around ₹41 per kg.', 'image' => 'https://investmentpoint.in/wp-content/uploads/2026/03/sugar-stocks-150x150.png'],
            ['title' => 'Need for a Change in Indian Market-Cap Classification', 'description' => 'Changes in company sizes, market conditions and participation have raised questions about the classification of companies by market capitalisation.', 'image' => 'https://investmentpoint.in/wp-content/uploads/2026/02/financial-markets-150x150.jpg'],
            ['title' => "Trump Bought and Sold Shares in Musk's SpaceX in July", 'description' => 'Reports have highlighted share purchases and sales connected with Elon Musk’s SpaceX during July by Donald Trump.', 'image' => 'https://investmentpoint.in/wp-content/uploads/2026/06/spacex-150x150.png'],
            ['title' => 'Mark Zuckerberg Adds $25 Billion in One Day as Meta Pushes AI', 'description' => 'Mark Zuckerberg’s wealth reportedly increased by $25 billion in a single day as Meta continued its focus on artificial intelligence.', 'image' => 'https://investmentpoint.in/wp-content/uploads/2026/09/mark-zuckerberg-150x150.jpg'],
            ['title' => 'RBI Signals Stable Rates as Inflation Remains Under Control', 'description' => 'The central bank maintained a steady line on rates as inflation cooled and domestic demand remained resilient.', 'image' => 'https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?auto=format&fit=crop&w=1200&q=80'],
            ['title' => 'IT Stocks Advance as Global Demand Outlook Improves', 'description' => 'Technology counters moved higher as global demand signals improved and export expectations strengthened.', 'image' => 'https://images.unsplash.com/photo-1526379095098-d400fd0bf935?auto=format&fit=crop&w=1200&q=80'],
        ] as $article) {
            News::firstOrCreate(['title' => $article['title']], $article + ['status' => 'active']);
        }

        // No sample Technical Analysis: stock levels are only ever entered by the admin

        foreach ([
            ['title' => 'Daily Market Outlook', 'video_thumbnail' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=500&q=75', 'video_url' => 'https://www.youtube.com/', 'published_date' => '2026-09-18'],
            ['title' => 'Top 5 Stocks to Watch', 'video_thumbnail' => 'https://images.unsplash.com/photo-1526304640581-d334cdbbf45e?auto=format&fit=crop&w=500&q=75', 'video_url' => 'https://www.youtube.com/', 'published_date' => '2026-09-17'],
            ['title' => 'Technical Analysis: Stocks to Watch', 'video_thumbnail' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=500&q=75', 'video_url' => 'https://www.youtube.com/', 'published_date' => '2026-09-16'],
        ] as $video) {
            // Placeholders only for an empty table, so real videos added in admin are never duplicated
            if (YoutubeVideo::count() < 3) {
                YoutubeVideo::firstOrCreate(['title' => $video['title']], $video);
            }
        }

        if (PremiumContent::exists()) {
            return;
        }

        PremiumContent::firstOrCreate(['title' => 'Unlock Premium Market Insights'], [
            'badge' => 'INVESTMENT POINT PREMIUM',
            'title' => 'Unlock Premium Market Insights',
            'description' => 'Get access to premium market content and exclusive insights.',
            'price' => 100.00,
            'billing_period' => 'MONTH',
            'benefits' => ['Advanced stock analysis', 'Expert views & research reports', 'Exclusive articles', 'Market alerts'],
            'button_text' => 'Subscribe Now',
            'button_url' => '#',
            'status' => 'active',
        ]);
    }
}
