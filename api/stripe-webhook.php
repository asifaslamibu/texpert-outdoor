<?php
// POST /api/stripe-webhook.php — Stripe calls this after a Checkout payment.
// In the Stripe dashboard add this URL as a webhook endpoint for the event checkout.session.completed,
// then put its signing secret (whsec_...) in includes/config.php.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/stripe.php';

$payload = file_get_contents('php://input') ?: '';
$secret = (string)config('stripe.webhook_secret', '');
if ($secret === '' || !stripe_verify_signature($payload, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '', $secret)) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
$session = $event['data']['object'] ?? [];
switch ($event['type'] ?? '') {
    case 'checkout.session.completed':
    case 'checkout.session.async_payment_succeeded':
        if (($session['payment_status'] ?? '') === 'paid') mark_order_paid((string)$session['id']);
        break;
    case 'checkout.session.async_payment_failed':
        query("UPDATE orders SET payment_status = 'failed' WHERE stripe_session_id = ?", [(string)$session['id']]);
        break;
}
http_response_code(200);
echo 'ok';
