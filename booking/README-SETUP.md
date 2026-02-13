# Booking setup

Files
- booking/index.html: Booking portal UI
- booking/api: PHP endpoints for requests and admin updates
- booking/data: JSON storage
- booking/admin/index.php: Admin panel (approval + availability)

Configure
1) Set an admin key in booking/api/config.php (ADMIN_API_KEY).
2) Protect /booking/admin with basic auth by setting ADMIN_UI_USER and ADMIN_UI_PASSWORD_HASH (leave the hash empty to use ADMIN_API_KEY as the password). You can generate a hash with: php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
3) Set tour city and timezone in booking/data/availability.json or via booking/api/admin/availability.php.
4) Set PAYPAL_ME_LINK, PAYPAL_CURRENCY, USDC_WALLET, BTC_WALLET (and network labels), plus email settings in booking/api/config.php. Set EMAIL_ENABLED to true once mail() works.
5) Optional: add Stripe keys in booking/api/config.php, then implement booking/api/stripe/create-checkout.php.
6) Restrict access to booking/api/admin endpoints in .htaccess or server config if possible.

Notes
- Requests are stored in booking/data/requests.json.
- The booking form posts to booking/api/request.php.
- 24+ hour requests should use WhatsApp.
