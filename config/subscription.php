<?php

/*
 * Subscription page: the plan, online payment (Razorpay) and the WhatsApp message sent after payment.
 *
 * Razorpay keys live in config/services.php (RAZORPAY_KEY / RAZORPAY_SECRET in .env):
 *  - no keys          -> DEMO: a "Complete demo payment" button stands in for Razorpay (nothing is charged)
 *  - rzp_test_ keys   -> Razorpay test mode (use Razorpay's test cards / UPI, nothing is charged)
 *  - rzp_live_ keys   -> real payments
 */
return [
    /*
     * The plans on the subscription page (prices are changed in Admin -> Site Settings -> Subscription prices).
     * 'deliver': what the form asks for - 'whatsapp' (the WhatsApp number) or 'post' (a postal address).
     * 'member_access': paying for it also opens the member page (WhatsApp OTP login, Member Picks).
     */
    'plans' => [
        'newsletter' => [
            'name' => 'Daily Digital News Letter',
            'price' => 8000,
            'months' => 1,
            'period' => 'month',
            'deliver' => 'whatsapp',
            'member_access' => true,
            'tagline' => 'Daily Digital News Letter On WhatsApp',   // the line under the price on the plan card (empty: none)
            'benefits' => [
                ['📊', 'Market Trend & Derivative Index Range'],
                ['📈', 'Support & Resistance Levels'],
                ['📑', 'Financial Awareness'],
            ],
        ],
        'newspaper' => [
            'name' => 'Weekly Gujarati News Paper',
            'price' => 10000,
            'months' => 12,
            'period' => 'year',
            'deliver' => 'post',
            'member_access' => false,
            'tagline' => 'Printed Paper By Post, Every Week',
            'benefits' => [
                ['📊', 'Weekly Market Trend & Analysis'],
                ['📈', 'Support & Resistance Levels'],
                ['🏦', 'Business & Stock Market News'],
                ['📑', 'Financial Awareness'],
            ],
        ],
    ],

    // Contact details, and the plan used when the admin adds a member by hand (Admin -> Members)
    'plan' => [
        'name' => 'Daily Digital News Letter',
        'price' => 8000,    // rupees
        'months' => 1,      // how long one payment lasts
        'contact_name' => 'Nikhil Bhatt',
        'contact_phone' => '+919939080808',
        'contact_email' => 'shreeinvestmentpoint@gmail.com',
    ],

    // The free "Complete demo payment" button while no Razorpay keys are set. OFF unless the .env says
    // SUBSCRIPTION_DEMO_PAYMENTS=true (local testing only): on the live site it would let anyone
    // subscribe - and log in - without paying. Demo-paid subscriptions stop giving access as soon
    // as this is off or real keys are added (see SubscriptionRequest::trustedModes).
    // Safety lock: never on when APP_ENV=production, whatever the .env flag says.
    'demo_payments' => (bool) env('SUBSCRIPTION_DEMO_PAYMENTS', false) && env('APP_ENV') !== 'production',

    // Member login: the subscriber's WhatsApp number + a one-time code (OTP) sent on WhatsApp.
    // Only numbers with a paid, running subscription can log in.
    //  - WhatsApp (Secondtick or Meta, see 'whatsapp' below) plus WHATSAPP_OTP_TEMPLATE: the name of an
    //    "Authentication" template approved by Meta (body "{{1}} is your verification code." + a "Copy code" button)
    //  - not set + OTP_DEMO=true -> DEMO: the code is shown on the login page instead of sent
    //    (LOCAL TESTING ONLY: on the live site anyone could log in as any subscriber)
    // A member stays logged in while they keep visiting; not opening the site for more than this many days
    // logs them out (a new OTP is needed). 0 = never log out for being away.
    'member_idle_days' => (int) env('MEMBER_IDLE_DAYS', 1),

    'otp' => [
        'whatsapp_template' => env('WHATSAPP_OTP_TEMPLATE'),
        'whatsapp_template_language' => env('WHATSAPP_OTP_TEMPLATE_LANGUAGE', 'en'),
        'demo' => (bool) env('OTP_DEMO', env('SMS_DEMO_OTP', false)) && env('APP_ENV') !== 'production', // safety lock, as above
        'length' => 6,
        'valid_minutes' => 5,
        'resend_seconds' => 30,
        'max_attempts' => 5,   // wrong codes before a new OTP is needed
        'max_per_hour' => 5,   // OTPs per WhatsApp number per hour
    ],

    // Admin -> Member Picks: "Send a WhatsApp reminder" tells every running subscriber that the stocks of a
    // date are added / updated, with their names (the levels themselves stay on the member page). Needs WHATSAPP_PICKS_TEMPLATE:
    // the name of a template approved by Meta whose body takes 3 values: {{1}} name, {{2}} date, {{3}} stock names.
    'picks' => [
        'whatsapp_template' => env('WHATSAPP_PICKS_TEMPLATE'),
        'whatsapp_template_language' => env('WHATSAPP_PICKS_TEMPLATE_LANGUAGE', 'en'),
        // Editing a stock that has an Achieved price: its own approved template with 4 body values, numbered
        // in the order they stand in the message: {{1}} date and time of the update (Indian time, the top line),
        // {{2}} name, {{3}} achieved price ("₹2,265"), {{4}} stock name.
        // Empty (or refused by WhatsApp): the template above is used.
        'achieved_template' => env('WHATSAPP_ACHIEVED_TEMPLATE'),
        'achieved_template_language' => env('WHATSAPP_ACHIEVED_TEMPLATE_LANGUAGE', env('WHATSAPP_PICKS_TEMPLATE_LANGUAGE', 'en')),
    ],

    // Who sends the WhatsApp messages (login OTP + "subscription active" + member picks reminder):
    //  - secondtick: Secondtick (app.secondtick.com) - API token + vendor UID from its Settings -> API Access page
    //  - meta:       WhatsApp Cloud API directly - token + phone number id below
    // Templates are the same either way (approved by Meta, listed under Templates in the Secondtick console).
    // Not set up: the message is not sent automatically; Admin -> Subscriptions shows a one-click
    // "Send on WhatsApp" link instead.
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', env('SECONDTICK_TOKEN') ? 'secondtick' : 'meta'),
        'secondtick' => [
            'base_url' => rtrim(env('SECONDTICK_API_URL', 'https://app.secondtick.com/api'), '/'),
            'vendor_uid' => env('SECONDTICK_VENDOR_UID'),
            'token' => env('SECONDTICK_TOKEN'),
            // Optional: send from a particular number of the account (empty = the account's default number)
            'from_phone_number_id' => env('SECONDTICK_FROM_PHONE_NUMBER_ID'),
        ],
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        // Business-started messages need a template approved by Meta. Its body takes 4 values:
        // {{1}} name, {{2}} plan, {{3}} start date, {{4}} expiry date.
        // Leave empty to send plain text (only delivered if the reader messaged you in the last 24 hours).
        'template' => env('WHATSAPP_TEMPLATE'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
    ],
];
