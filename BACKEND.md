# Texpert Outdoors backend

PHP 8.1+ and MySQL/MariaDB, no frameworks or Composer. Runs on Hostinger shared hosting and on XAMPP.

## What it does

| Area | Where |
|---|---|
| Admin panel: dashboard, orders, products (with image upload), categories, customers, contact queries, subscribers, admin users | `/admin/` |
| Bulk product import from CSV (creates missing categories, copies images, re-import updates price and stock) | `/admin/import.php`, `includes/importer.php` |
| Live catalog for the storefront | `api/catalog.php` (loaded before `assets/js/data.js`) |
| Checkout, prices and stock checked on the server | `api/checkout.php` |
| Stripe card payments (hosted Checkout + webhook) | `api/checkout.php`, `api/pay.php`, `api/stripe-webhook.php` |
| Order confirmation and tracking | `api/order.php`, `api/track.php` |
| Customer accounts (sign up, sign in, order history, saved address) | `api/account.php`, `account.html` |
| Contact form and newsletter / SMS sign-ups | `api/contact.php`, `api/subscribe.php` |

If the backend is not configured or the database is down, the storefront keeps working with the built-in catalog in `data.js`, and checkout / contact fall back to opening the visitor's email app.

## Setting it up on Hostinger

1. **Create a database.** hPanel → Databases → MySQL Databases. Note the database name, user and password (they look like `u790051230_texpert`).
2. **Create the config.** Copy `includes/config.sample.php` to `includes/config.php` and fill in:
   - `db` → the values from step 1 (host is usually `localhost`)
   - `install_token` → any long random string
   - `stripe.secret_key` → from the Stripe dashboard (start with `sk_test_...`)
   - `site_url` → `https://www.texpertoutdoors.com`
   Upload `includes/config.php` by FTP. It is in `.gitignore` and must never be committed.
3. **Run the installer.** Open `https://www.texpertoutdoors.com/install/?token=YOUR_INSTALL_TOKEN`. It creates the tables, imports the 50 starter products and asks for the first admin account. Afterwards delete the `install` folder from the server.
4. **Sign in** at `https://www.texpertoutdoors.com/admin/`.

## Stripe

1. Put the secret key in `includes/config.php`. The checkout page then shows "Pay Now by Card".
2. In Stripe → Developers → Webhooks, add the endpoint `https://www.texpertoutdoors.com/api/stripe-webhook.php` with the event `checkout.session.completed`, and copy its signing secret (`whsec_...`) into `stripe.webhook_secret`.
3. Test with card `4242 4242 4242 4242`, any future date and any CVC. Switch to the live key (`sk_live_...`) when ready.

Card details never touch this server: customers pay on Stripe's hosted page.

## Local development (XAMPP)

```
mysql -uroot -e "CREATE DATABASE texpert_outdoors CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cp includes/config.sample.php includes/config.php   # set install_token
```
Then open `http://localhost/texpert-outdoor/install/?token=...`.

## Security notes

- `includes/` and script execution in `uploads/` are blocked by `.htaccess`.
- Passwords use `password_hash`; admin forms have CSRF tokens; all SQL uses prepared statements.
- Order totals are always recalculated from database prices; the browser's prices are ignored.
- Order emails use PHP `mail()`. If they do not arrive, set up an SMTP mailbox in hPanel for `from_email`.
