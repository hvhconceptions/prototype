<?php
declare(strict_types=1);

require __DIR__ . '/../api/config.php';

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$store = read_json_file(DATA_DIR . '/requests.json', ['requests' => []]);
$requests = $store['requests'] ?? [];
if (!is_array($requests)) {
    $requests = [];
}

$request = null;
foreach ($requests as $item) {
    if (is_array($item) && ($item['id'] ?? '') === $id) {
        $request = $item;
        break;
    }
}

if (!$request) {
    http_response_code(404);
    echo 'Payment request not found.';
    exit;
}

$method = strtolower((string) ($request['payment_method'] ?? 'paypal'));
$deposit = isset($request['deposit_amount']) ? (int) $request['deposit_amount'] : 0;
$currency = (string) ($request['deposit_currency'] ?? (PAYPAL_CURRENCY !== '' ? PAYPAL_CURRENCY : 'USD'));
$depositPercent = (string) ($request['deposit_percent'] ?? '');
$details = build_payment_details($method, $deposit);
$isUrl = preg_match('/^https?:\\/\\//i', $details) === 1;
$paypalClientId = defined('PAYPAL_CLIENT_ID') ? (string) PAYPAL_CLIENT_ID : '';
$paypalCurrency = PAYPAL_CURRENCY !== '' ? PAYPAL_CURRENCY : 'CAD';
$showPaypalButtons = $method === 'paypal' && $deposit > 0 && $paypalClientId !== '';
$paypalAmount = number_format((float) $deposit, 2, '.', '');

$mailto = '';
if (in_array($method, ['interac', 'etransfer', 'e-transfer'], true) && INTERAC_EMAIL !== '') {
    $mailto = 'mailto:' . INTERAC_EMAIL;
}
if ($method === 'wise' && WISE_EMAIL !== '') {
    $mailto = 'mailto:' . WISE_EMAIL;
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Payment Details</title>
    <style>
      :root {
        --bg: #fff5fb;
        --ink: #12040a;
        --hot: #ff006e;
        --line: rgba(255, 0, 110, 0.25);
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        font-family: "Courier Prime", "IBM Plex Mono", "Courier New", Courier, monospace;
        background: radial-gradient(circle at 15% 10%, #ffe1f0 0%, #fff5fb 45%, #fff 100%);
        color: var(--ink);
        padding: 40px 20px;
      }

      main {
        max-width: 720px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 16px 32px rgba(255, 0, 110, 0.18);
      }

      h1 {
        margin: 0 0 12px;
        color: var(--hot);
        font-size: 1.8rem;
      }

      .row {
        margin: 10px 0;
      }

      .label {
        font-weight: 700;
      }

      .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-top: 14px;
        padding: 10px 16px;
        border-radius: 999px;
        border: 1px solid var(--hot);
        color: var(--hot);
        text-decoration: none;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 0.85rem;
      }

      .hidden {
        display: none;
      }

      .muted {
        color: #7a1c45;
        font-size: 0.9rem;
      }
    </style>
  </head>
  <body>
    <main>
      <h1>Payment details</h1>
      <div class="row"><span class="label">Name:</span> <?php echo htmlspecialchars((string) ($request['name'] ?? '')); ?></div>
      <div class="row"><span class="label">Method:</span> <?php echo htmlspecialchars(format_payment_method($method)); ?></div>
      <div class="row">
        <span class="label">Deposit:</span>
        <?php echo htmlspecialchars((string) $deposit); ?>
        <?php echo htmlspecialchars((string) $currency); ?>
        <?php if ($depositPercent !== '') : ?>
          (<?php echo htmlspecialchars($depositPercent); ?>%)
        <?php endif; ?>
      </div>

      <?php if ($deposit <= 0) : ?>
        <p class="muted">No deposit selected. Your request is noted, but the time is not held.</p>
      <?php else : ?>
        <div class="row"><span class="label">Payment info:</span> <?php echo htmlspecialchars($details); ?></div>
      <?php endif; ?>

      <?php if ($showPaypalButtons) : ?>
        <p class="muted">On the payment page, they can pay by card or PayPal.</p>
        <div class="row"><span class="label">Pay by card or PayPal:</span></div>
        <div id="paypal-buttons"></div>
        <div id="paypal-fallback" class="row hidden">
          <a class="btn" href="<?php echo htmlspecialchars($details); ?>" target="_blank" rel="noopener">Open PayPal</a>
        </div>
        <p id="payment-status" class="muted"></p>
        <script>
          const paypalStatus = document.getElementById("payment-status");
          const paypalFallback = document.getElementById("paypal-fallback");
          const showPaypalFallback = (message) => {
            if (paypalFallback) {
              paypalFallback.classList.remove("hidden");
            }
            if (paypalStatus) {
              paypalStatus.textContent = message;
            }
          };

          const renderPaypalButtons = () => {
            if (!window.paypal) {
              showPaypalFallback("PayPal failed to load. Use the PayPal link instead.");
              return;
            }
            window.paypal
              .Buttons({
                style: { layout: "vertical", color: "gold", shape: "pill", label: "pay" },
                createOrder: (data, actions) =>
                  actions.order.create({
                    purchase_units: [
                      {
                        amount: {
                          value: <?php echo json_encode($paypalAmount); ?>,
                          currency_code: <?php echo json_encode($paypalCurrency); ?>,
                        },
                        description: <?php echo json_encode('Booking deposit ' . $id); ?>,
                      },
                    ],
                  }),
                onApprove: (data, actions) =>
                  actions.order.capture().then(() => {
                    if (paypalStatus) {
                      paypalStatus.textContent =
                        "Payment received. We will confirm your booking shortly.";
                    }
                  }),
                onError: () => {
                  showPaypalFallback("Payment error. Use the PayPal link or contact me.");
                },
              })
              .render("#paypal-buttons");
          };
        </script>
        <script
          src="https://www.paypal.com/sdk/js?client-id=<?php echo htmlspecialchars($paypalClientId); ?>&currency=<?php echo htmlspecialchars($paypalCurrency); ?>&intent=capture&enable-funding=card&components=buttons"
          onerror="window.__paypalLoadFailed = true;"
        ></script>
        <script>
          if (window.__paypalLoadFailed) {
            showPaypalFallback("PayPal failed to load. Use the PayPal link instead.");
          } else {
            renderPaypalButtons();
          }
        </script>
      <?php elseif ($isUrl && $deposit > 0) : ?>
        <a class="btn" href="<?php echo htmlspecialchars($details); ?>" target="_blank" rel="noopener">Open payment link</a>
        <p class="muted">Redirecting now...</p>
        <script>
          window.setTimeout(() => {
            window.location.href = <?php echo json_encode($details); ?>;
          }, 900);
        </script>
      <?php elseif ($mailto && $deposit > 0) : ?>
        <a class="btn" href="<?php echo htmlspecialchars($mailto); ?>">Email payment address</a>
      <?php endif; ?>
    </main>
  </body>
</html>
