<?php

return [
    /*
     | Address of the admin panel: https://your-site/{path}. Change it on the live site to something
     | only you know (e.g. ADMIN_PATH=ip-control-7391) so bots can't find the login page.
     */
    'path' => trim(env('ADMIN_PATH', 'admin'), '/') ?: 'admin',

    // Log out automatically after this many minutes without any click in the admin panel
    'idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 60),

    // Admin panel works on every device. Set ADMIN_DESKTOP_ONLY=true to allow computers / laptops only
    'desktop_only' => (bool) env('ADMIN_DESKTOP_ONLY', false),

    // Wrong passwords allowed per username + IP before the login is locked, and for how long
    'max_attempts' => 5,
    'lockout_minutes' => 15,
];
