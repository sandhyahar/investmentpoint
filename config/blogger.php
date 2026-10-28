<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-post to Blogger (Admin -> Site Settings -> Blogger)
    |--------------------------------------------------------------------------
    |
    | E-Paper editions and Media Articles uploaded in admin are posted to the same
    | blogspot their section is imported from (config/epaper.php), once the admin
    | has connected the Google account that owns the blogs. Posts go out right
    | after saving, and every few minutes the scheduler (blogger:publish) posts
    | any that were missed or scheduled for later.
    |
    */

    // Switch auto-posting off without disconnecting the account
    'enabled' => env('BLOGGER_AUTO_POST', true),

    // The Media Article blogspot (the E-Paper blogs are in config/epaper.php)
    'media_blog' => 'https://mediaarticle-ipoint.blogspot.com',

    // The Google account that owns the blogs: suggested on Google's sign-in page
    'account' => env('BLOGGER_ACCOUNT', 'shreeinvestmentpoint@gmail.com'),

    // A post that fails this many times is not tried again (the error is shown in admin)
    'max_tries' => 5,

    // Daily E-Papers waiting to be emailed to Blogger (App\Services\EpaperByEmail); empty = storage/app/epaper-outbox
    'outbox' => env('BLOGGER_OUTBOX'),
];
