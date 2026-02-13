<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

if (STRIPE_SECRET_KEY === '') {
    json_response(['error' => 'Stripe not configured'], 501);
}

json_response(['error' => 'Stripe endpoint not implemented yet'], 501);
