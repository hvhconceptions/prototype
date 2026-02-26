<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../api/config.php';
if (!file_exists($configPath)) {
    $alternatePath = __DIR__ . '/../../api/config.php';
    if (file_exists($alternatePath)) {
        $configPath = $alternatePath;
    }
}

if (!file_exists($configPath)) {
    http_response_code(500);
    echo 'Admin config missing.';
    exit;
}

require_once $configPath;

const ADMIN_RATE_LIMIT_MAX = 3;
const ADMIN_RATE_LIMIT_WINDOW = 900;
const ADMIN_RATE_LIMIT_BLOCK = 1800;
const ADMIN_RATE_LIMIT_FILE = DATA_DIR . '/admin_rate_limit.json';

function admin_get_client_ip(): string
{
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($ip !== '') {
        return trim(explode(',', $ip)[0]);
    }
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        return trim(explode(',', $forwarded)[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function load_admin_rate_state(): array
{
    return read_json_file(ADMIN_RATE_LIMIT_FILE, ['ips' => []]);
}

function normalize_admin_rate_record(array $record, int $now): array
{
    $count = (int) ($record['count'] ?? 0);
    $first = (int) ($record['first'] ?? $now);
    $blockedUntil = (int) ($record['blocked_until'] ?? 0);
    $strikes = (int) ($record['strikes'] ?? 0);
    if ($blockedUntil < 0) {
        $blockedUntil = 0;
    }
    if ($blockedUntil > $now) {
        return ['count' => $count, 'first' => $first, 'blocked_until' => $blockedUntil, 'strikes' => $strikes];
    }
    if (($now - $first) > ADMIN_RATE_LIMIT_WINDOW) {
        return ['count' => 0, 'first' => $now, 'blocked_until' => 0, 'strikes' => $strikes];
    }
    return ['count' => $count, 'first' => $first, 'blocked_until' => $blockedUntil, 'strikes' => $strikes];
}

function record_admin_failure(array $state, string $ip, array $record, int $now): void
{
    $count = (int) ($record['count'] ?? 0) + 1;
    $first = (int) ($record['first'] ?? $now);
    $blockedUntil = max(0, (int) ($record['blocked_until'] ?? 0));
    $strikes = (int) ($record['strikes'] ?? 0);
    if (($now - $first) > ADMIN_RATE_LIMIT_WINDOW) {
        $count = 1;
        $first = $now;
        $blockedUntil = 0;
    }
    if ($count >= ADMIN_RATE_LIMIT_MAX) {
        $strikes += 1;
        $multiplier = max(1, min($strikes, 8));
        $blockedUntil = $now + (ADMIN_RATE_LIMIT_BLOCK * $multiplier);
        $count = 0;
        $first = $now;
    }
    $state['ips'][$ip] = [
        'count' => $count,
        'first' => $first,
        'blocked_until' => $blockedUntil,
        'strikes' => $strikes,
    ];
    write_json_file(ADMIN_RATE_LIMIT_FILE, $state);
}

function clear_admin_rate_record(array $state, string $ip): void
{
    if (isset($state['ips'][$ip])) {
        unset($state['ips'][$ip]);
        write_json_file(ADMIN_RATE_LIMIT_FILE, $state);
    }
}

function deny_rate_limit(): void
{
    http_response_code(429);
    echo 'Too many attempts. Try again later.';
    exit;
}

function get_basic_auth_credentials(): array
{
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $pass = $_SERVER['PHP_AUTH_PW'] ?? '';
    if ($user !== '' || $pass !== '') {
        return [$user, $pass];
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && isset($_SERVER['Authorization'])) {
        $header = (string) $_SERVER['Authorization'];
    }
    if ($header !== '' && stripos($header, 'basic ') === 0) {
        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded !== false) {
            $parts = explode(':', $decoded, 2);
            return [$parts[0] ?? '', $parts[1] ?? ''];
        }
    }
    return ['', ''];
}

function deny_admin_access(): void
{
    header('WWW-Authenticate: Basic realm="Booking Admin"');
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

function require_admin_ui(): void
{
    if (ADMIN_API_KEY === '' || ADMIN_API_KEY === 'change-this-admin-key') {
        http_response_code(500);
        echo 'Admin key not configured.';
        exit;
    }
    $ip = admin_get_client_ip();
    $now = time();
    $state = load_admin_rate_state();
    $record = normalize_admin_rate_record($state['ips'][$ip] ?? [], $now);
    $blockedUntil = (int) ($record['blocked_until'] ?? 0);
    if ($blockedUntil > $now) {
        deny_rate_limit();
    }
    [$user, $pass] = get_basic_auth_credentials();
    $expectedUser = defined('ADMIN_UI_USER') && ADMIN_UI_USER !== '' ? ADMIN_UI_USER : 'admin';
    if ($user === '' || $pass === '') {
        deny_admin_access();
    }
    if (defined('ADMIN_UI_PASSWORD_HASH') && ADMIN_UI_PASSWORD_HASH !== '') {
        if (!hash_equals($expectedUser, (string) $user) || !password_verify($pass, ADMIN_UI_PASSWORD_HASH)) {
            record_admin_failure($state, $ip, $record, $now);
            deny_admin_access();
        }
        clear_admin_rate_record($state, $ip);
        return;
    }
    if (!hash_equals($expectedUser, (string) $user) || !hash_equals(ADMIN_API_KEY, (string) $pass)) {
        record_admin_failure($state, $ip, $record, $now);
        deny_admin_access();
    }
    clear_admin_rate_record($state, $ip);
}

require_admin_ui();

function resolve_request_timezone(array $request): DateTimeZone
{
    $fallback = defined('DEFAULT_TOUR_TZ') && DEFAULT_TOUR_TZ !== '' ? DEFAULT_TOUR_TZ : 'America/Toronto';
    $timezoneName = trim((string) ($request['tour_timezone'] ?? $fallback));
    if ($timezoneName === '') {
        $timezoneName = $fallback;
    }
    try {
        return new DateTimeZone($timezoneName);
    } catch (Throwable $exception) {
        return new DateTimeZone('UTC');
    }
}

function resolve_request_end_at(array $request): ?DateTimeImmutable
{
    $date = trim((string) ($request['preferred_date'] ?? ''));
    $time = trim((string) ($request['preferred_time'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
        return null;
    }
    $timezone = resolve_request_timezone($request);
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $timezone);
    if (!$start instanceof DateTimeImmutable) {
        return null;
    }
    $hours = is_numeric($request['duration_hours'] ?? null) ? (float) $request['duration_hours'] : 0.0;
    $minutes = (int) round($hours * 60);
    if ($minutes <= 0) {
        $minutes = 60;
    }
    return $start->modify('+' . $minutes . ' minutes');
}

function has_request_passed(array $request, DateTimeImmutable $nowUtc): bool
{
    $end = resolve_request_end_at($request);
    if (!$end instanceof DateTimeImmutable) {
        return false;
    }
    return $end->setTimezone(new DateTimeZone('UTC')) < $nowUtc;
}

function is_customer_directory_status(string $status): bool
{
    return in_array($status, ['cancelled', 'declined', 'rejected', 'blacklisted'], true);
}

function include_in_customer_directory(array $request, DateTimeImmutable $nowUtc): bool
{
    $source = strtolower(trim((string) ($request['__source'] ?? '')));
    if ($source === 'declined') {
        return true;
    }
    $status = strtolower(trim((string) ($request['status'] ?? '')));
    if (is_customer_directory_status($status)) {
        return true;
    }
    $cancelReason = trim((string) ($request['cancel_reason'] ?? ''));
    $declineReason = trim((string) ($request['decline_reason'] ?? ''));
    $blacklistReason = trim((string) ($request['blacklist_reason'] ?? ''));
    if ($cancelReason !== '' || $declineReason !== '' || $blacklistReason !== '') {
        return true;
    }
    return has_request_passed($request, $nowUtc);
}

$store = read_json_file(DATA_DIR . '/requests.json', ['requests' => []]);
$requests = $store['requests'] ?? [];
if (!is_array($requests)) {
    $requests = [];
}
$declinedStore = read_json_file(DATA_DIR . '/declined.json', ['requests' => []]);
$declinedRequests = $declinedStore['requests'] ?? [];
if (!is_array($declinedRequests)) {
    $declinedRequests = [];
}
$hiddenStore = read_json_file(DATA_DIR . '/customers_hidden.json', ['hidden' => []]);
$hiddenKeys = $hiddenStore['hidden'] ?? [];
if (!is_array($hiddenKeys)) {
    $hiddenKeys = [];
}
$hiddenKeys = array_map('strval', $hiddenKeys);
$hiddenMap = array_fill_keys($hiddenKeys, true);
$allRequests = [];
foreach ($requests as $request) {
    if (!is_array($request)) {
        continue;
    }
    $request['__source'] = 'requests';
    $allRequests[] = $request;
}
foreach ($declinedRequests as $request) {
    if (!is_array($request)) {
        continue;
    }
    $request['__source'] = 'declined';
    $allRequests[] = $request;
}
$nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

$customers = [];
foreach ($allRequests as $request) {
    if (!include_in_customer_directory($request, $nowUtc)) {
        continue;
    }
    $email = strtolower(trim((string) ($request['email'] ?? '')));
    $phone = trim((string) ($request['phone'] ?? ''));
    $key = $email !== '' ? $email : ($phone !== '' ? $phone : (string) ($request['id'] ?? ''));
    if ($key === '') {
        continue;
    }
    if (isset($hiddenMap[$key])) {
        continue;
    }
    $current = $customers[$key] ?? null;
    $created = (string) ($request['created_at'] ?? '');
    if ($current === null || strcmp($created, (string) ($current['created_at'] ?? '')) > 0) {
        $request['__key'] = $key;
        $customers[$key] = $request;
    }
}

$customerList = array_values($customers);
usort($customerList, function (array $a, array $b): int {
    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

$count = count($customerList);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BombaCLOUD! Client Directory</title>
    <style>
      :root {
        --bg: #fff5fb;
        --ink: #12040a;
        --hot: #ff006e;
        --line: rgba(255, 0, 110, 0.25);
        --shadow: rgba(255, 0, 110, 0.2);
        --mono: "Courier Prime", "IBM Plex Mono", "Courier New", Courier, monospace;
        --bubble: "Baloo 2", "Cooper Black", "Bookman Old Style", "Georgia", serif;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        font-family: var(--mono);
        color: var(--ink);
        background: radial-gradient(circle at 15% 10%, #ffe1f0 0%, #fff5fb 45%, #fff 100%);
      }

      header {
        max-width: 1100px;
        margin: 0 auto;
        padding: 48px 24px 24px;
      }

      h1 {
        font-family: var(--bubble);
        color: var(--hot);
        margin: 0 0 8px;
        font-size: clamp(2rem, 4vw, 3rem);
      }

      h2 {
        margin: 0 0 12px;
        font-size: 1rem;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: var(--hot);
      }

      .subtitle {
        margin: 0;
        color: #55122b;
        font-size: 0.95rem;
      }

      main {
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 24px 64px;
      }

      .card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 22px;
        padding: 20px;
        box-shadow: 0 16px 32px var(--shadow);
      }

      .row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        margin-bottom: 16px;
      }

      .btn {
        padding: 10px 16px;
        border-radius: 999px;
        border: 1px solid var(--hot);
        background: transparent;
        color: var(--hot);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }

      input {
        padding: 8px 12px;
        border-radius: 999px;
        border: 1px solid var(--line);
        background: #fff9fc;
        font-family: var(--mono);
        color: var(--ink);
      }

      table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
      }

      th,
      td {
        text-align: left;
        padding: 10px 12px;
        border-bottom: 1px solid rgba(255, 0, 110, 0.15);
        vertical-align: top;
      }

      th {
        text-transform: uppercase;
        letter-spacing: 0.14em;
        font-size: 0.75rem;
        color: #6b173f;
      }

      .muted {
        color: #7a1c45;
        font-size: 0.85rem;
      }

      .hidden-count {
        display: none;
      }

      @media (max-width: 900px) {
        table,
        tbody,
        tr,
        td,
        th {
          display: block;
        }
        tr {
          border-bottom: 1px solid rgba(255, 0, 110, 0.2);
          margin-bottom: 14px;
        }
        th {
          padding-top: 18px;
        }
      }
    </style>
  </head>
  <body>
    <header>
      <h1>BombaCLOUD! Client Directory</h1>
      <span id="customerCount" class="hidden-count"><?php echo $count; ?></span>
    </header>
    <main>
      <div class="card">
        <h2>Exports & contacts</h2>
        <p class="subtitle">
          CSV is for spreadsheets and contact imports. JSON is for backups or developers.
        </p>
        <p class="subtitle">
          To save contacts to your phone: download CSV, import it into Google Contacts (or iCloud), then sync your phone
          contacts.
        </p>
      </div>
      <div class="card">
        <div class="row">
          <a class="btn" href="index.php">Back to admin</a>
          <button class="btn" id="downloadCsv" type="button">Download CSV</button>
          <button class="btn" id="downloadJson" type="button">Download JSON</button>
          <span class="muted" id="downloadStatus"></span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>City</th>
              <th>Last request</th>
              <th>Status</th>
              <th>Delete</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$customerList) : ?>
            <tr data-empty-row="true">
              <td colspan="7" class="muted">No customers yet.</td>
            </tr>
            <?php else : ?>
            <?php foreach ($customerList as $customer) : ?>
            <tr data-customer-row="true">
              <td><?php echo htmlspecialchars((string) ($customer['name'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string) ($customer['email'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string) ($customer['phone'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string) ($customer['city'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string) ($customer['created_at'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string) ($customer['status'] ?? '')); ?></td>
              <td>
                <button class="btn ghost" type="button" data-delete-key="<?php echo htmlspecialchars((string) ($customer['__key'] ?? ''), ENT_QUOTES); ?>">Delete</button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
    <script>
      const ADMIN_KEY = <?php echo json_encode(ADMIN_API_KEY); ?>;
      const downloadCsvBtn = document.getElementById("downloadCsv");
      const downloadJsonBtn = document.getElementById("downloadJson");
      const downloadStatus = document.getElementById("downloadStatus");
      const customerCount = document.getElementById("customerCount");

      const getKey = () => ADMIN_KEY;

      const getDownloadFilename = (response, fallback) => {
        const disposition = response.headers.get("Content-Disposition") || "";
        const match = disposition.match(/filename="?([^\";]+)"?/i);
        return match ? match[1] : fallback;
      };

      const downloadRequests = async (format) => {
        const key = getKey();
        if (!key) {
          downloadStatus.textContent = "Admin key required.";
          return;
        }
        downloadStatus.textContent = "";
        try {
          const response = await fetch(`../api/admin/export.php?format=${encodeURIComponent(format)}`, {
            headers: { "X-Admin-Key": key },
          });
          if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || "download");
          }
          const blob = await response.blob();
          const filename = getDownloadFilename(response, `booking-requests.${format}`);
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement("a");
          link.href = url;
          link.download = filename;
          document.body.appendChild(link);
          link.click();
          link.remove();
          window.URL.revokeObjectURL(url);
          downloadStatus.textContent = "Download ready.";
        } catch (_error) {
          downloadStatus.textContent = "Failed to download.";
        }
      };

      if (downloadCsvBtn) {
        downloadCsvBtn.addEventListener("click", () => downloadRequests("csv"));
      }
      if (downloadJsonBtn) {
        downloadJsonBtn.addEventListener("click", () => downloadRequests("json"));
      }

      document.querySelectorAll("[data-delete-key]").forEach((btn) => {
        btn.addEventListener("click", async () => {
          const key = getKey();
          if (!key) {
            downloadStatus.textContent = "Admin key required.";
            return;
          }
          const targetKey = btn.dataset.deleteKey || "";
          if (!targetKey) return;
          if (!confirm("Delete this customer from the directory?")) return;
          downloadStatus.textContent = "";
          try {
            const response = await fetch("../api/admin/delete-customer.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-Admin-Key": key,
              },
              body: JSON.stringify({ key: targetKey }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || "delete");
            const row = btn.closest("tr");
            if (row) row.remove();
            if (customerCount) {
              const current = Number(customerCount.textContent || 0);
              if (!Number.isNaN(current) && current > 0) {
                customerCount.textContent = String(current - 1);
              }
            }
            if (customerCount && Number(customerCount.textContent || 0) <= 0) {
              const tbody = document.querySelector("tbody");
              if (tbody && !tbody.querySelector("[data-empty-row]")) {
                const emptyRow = document.createElement("tr");
                emptyRow.dataset.emptyRow = "true";
                emptyRow.innerHTML = '<td colspan="7" class="muted">No customers yet.</td>';
                tbody.appendChild(emptyRow);
              }
            }
            downloadStatus.textContent = "Customer deleted.";
          } catch (_error) {
            downloadStatus.textContent = "Failed to delete customer.";
          }
        });
      });
    </script>
  </body>
</html>
