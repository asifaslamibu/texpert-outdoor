<?php
// Copy this file to config.php and fill in your own values. config.php is never committed to git.
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'texpert_outdoors',
        'user' => 'root',
        'pass' => '',
    ],

    // Stripe keys from https://dashboard.stripe.com/apikeys (use sk_test_... while testing).
    // Leave secret_key empty to turn card payments off.
    'stripe' => [
        'secret_key'     => '',
        'webhook_secret' => '', // whsec_... from the webhook endpoint pointing at /api/stripe-webhook.php
        'currency'       => 'usd',
    ],

    // Public site address without a trailing slash, e.g. https://www.texpertoutdoors.com
    // Leave empty to detect it from the request.
    'site_url' => '',

    // New orders and messages are emailed here (PHP mail()). Leave empty to turn off.
    'notify_email' => 'sales@texpertoutdoors.com',
    'from_email'   => 'no-reply@texpertoutdoors.com',

    // Long random string. Required to open /install/ and create the first admin.
    'install_token' => 'change-me-to-a-long-random-string',

    'shipping' => [
        'free_over' => 75,
        'flat_rate' => 9.95,
    ],
];
