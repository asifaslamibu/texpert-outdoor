<?php
// POST /api/subscribe.php {type: email|sms, value} — newsletter / SMS sign-up from the footer.

require_once __DIR__ . '/../includes/api.php';

$in = json_input();
throttle('subscribe', 10, 600);

$type = ($in['type'] ?? '') === 'sms' ? 'sms' : 'email';
$value = str_field($in, 'value', 190);
if ($type === 'email') {
    $value = strtolower($value);
    if (!valid_email($value)) json_error('Please enter a valid email address.');
} else {
    $digits = preg_replace('/\D/', '', $value);
    if (strlen($digits) < 10 || strlen($digits) > 15) json_error('Please enter a valid mobile number.');
    $value = $digits;
}

query('INSERT IGNORE INTO subscribers (type, value) VALUES (?, ?)', [$type, $value]);
json_out(['ok' => true]);
