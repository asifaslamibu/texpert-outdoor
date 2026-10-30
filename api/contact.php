<?php
// POST /api/contact.php {name, email, phone, topic, message} — saves a contact query for the admin panel.

require_once __DIR__ . '/../includes/api.php';

$in = json_input();
if (!empty($in['website'])) json_out(['ok' => true]); // honeypot field filled in by bots
throttle('contact', 5, 600);

$name = str_field($in, 'name', 120);
$email = str_field($in, 'email', 190);
$message = str_field($in, 'message', 5000);
if ($name === '') json_error('Please enter your name.');
if (!valid_email($email)) json_error('Please enter a valid email address.');
if (mb_strlen($message) < 5) json_error('Please write a short message.');

$topic = str_field($in, 'topic', 80);
$phone = str_field($in, 'phone', 40);
query('INSERT INTO messages (name, email, phone, topic, message) VALUES (?, ?, ?, ?, ?)', [$name, $email, $phone, $topic, $message]);
$id = (int)db()->lastInsertId();

notify((string)config('notify_email', ''), "New message: $topic from $name",
    "$message\n\nName: $name\nEmail: $email\nPhone: $phone\n\n" . site_url() . "/admin/messages.php?id=$id");

json_out(['ok' => true]);
