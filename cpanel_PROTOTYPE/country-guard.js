(() => {
  const blocked = new Set(["MA"]);
  const cacheKey = "hvh-country-guard-v1";
  const cacheTtlMs = 6 * 60 * 60 * 1000;

  const deny = (countryCode) => {
    document.documentElement.innerHTML =
      '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access Restricted</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#050505;color:#f4f4f4;font:16px/1.5 Arial,sans-serif;padding:24px}.card{max-width:560px;border:1px solid #2c2c2c;background:#101010;border-radius:14px;padding:22px}h1{margin:0 0 10px;font-size:26px}p{margin:0;color:#c6c6c6}</style></head><body><div class="card"><h1>Access Restricted</h1><p>This website is unavailable from your region.</p></div></body></html>';
    if (countryCode) {
      try {
        sessionStorage.setItem(
          cacheKey,
          JSON.stringify({ country: countryCode, ts: Date.now() })
        );
      } catch (_) {}
    }
  };

  const isBlocked = (countryCode) => blocked.has(String(countryCode || "").toUpperCase());

  try {
    const raw = sessionStorage.getItem(cacheKey);
    if (raw) {
      const parsed = JSON.parse(raw);
      if (parsed && Date.now() - Number(parsed.ts || 0) < cacheTtlMs) {
        if (isBlocked(parsed.country)) {
          deny(parsed.country);
          return;
        }
      }
    }
  } catch (_) {}

  fetch("https://ipapi.co/country/", { cache: "no-store" })
    .then((r) => (r.ok ? r.text() : ""))
    .then((code) => String(code || "").trim().toUpperCase())
    .then((countryCode) => {
      try {
        sessionStorage.setItem(
          cacheKey,
          JSON.stringify({ country: countryCode, ts: Date.now() })
        );
      } catch (_) {}
      if (isBlocked(countryCode)) {
        deny(countryCode);
      }
    })
    .catch(() => {});
})();
