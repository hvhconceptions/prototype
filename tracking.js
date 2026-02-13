(function () {
  const global = typeof window !== "undefined" ? window : {};
  const defaultConfig = {
    endpoint: "",
    siteId: "default-site",
    debug: false,
  };

  const config = Object.assign({}, defaultConfig, global.hvhTrackingConfig || {});
  global.hvhTrackingConfig = config;

  const { endpoint } = config;
  const debug = Boolean(config.debug);
  const siteId = String(config.siteId || "default-site");
  const endpointReady = typeof endpoint === "string" && endpoint.trim().length > 0;

  const log = (...args) => {
    if (debug) {
      console.log("[HVH Tracker]", ...args);
    }
  };

  const warn = (...args) => {
    if (debug) {
      console.warn("[HVH Tracker]", ...args);
    }
  };

  const safeStorage = {
    get(key) {
      try {
        return window.localStorage.getItem(key);
      } catch (_error) {
        return null;
      }
    },
    set(key, value) {
      try {
        window.localStorage.setItem(key, value);
      } catch (_error) {
        /* ignore */
      }
    },
    getSession(key) {
      try {
        return window.sessionStorage.getItem(key);
      } catch (_error) {
        return null;
      }
    },
    setSession(key, value) {
      try {
        window.sessionStorage.setItem(key, value);
      } catch (_error) {
        /* ignore */
      }
    },
  };

  const VISITOR_KEY = "hvh_tracker_visitor";
  const SESSION_KEY = "hvh_tracker_session";

  const randomId = () => {
    if (global.crypto && typeof global.crypto.randomUUID === "function") {
      return global.crypto.randomUUID();
    }
    return `${Date.now().toString(36)}-${Math.random().toString(16).slice(2, 10)}`;
  };

  let visitorId = safeStorage.get(VISITOR_KEY);
  if (!visitorId) {
    visitorId = randomId();
    safeStorage.set(VISITOR_KEY, visitorId);
  }

  let sessionId = safeStorage.getSession(SESSION_KEY);
  if (!sessionId) {
    sessionId = randomId();
    safeStorage.setSession(SESSION_KEY, sessionId);
  }

  const pageContext = {
    url: String(location.href),
    path: String(location.pathname + location.search + location.hash),
    referrer: document.referrer || "",
  };

  const basePayload = () => ({
    siteId,
    visitorId,
    sessionId,
    timestamp: new Date().toISOString(),
    url: String(location.href),
    path: String(location.pathname + location.search + location.hash),
    referrer: pageContext.referrer,
    title: document.title,
    language: navigator.language || "",
    viewport: {
      width: window.innerWidth || 0,
      height: window.innerHeight || 0,
    },
    screen: {
      width: window.screen ? window.screen.width : 0,
      height: window.screen ? window.screen.height : 0,
    },
    tzOffset: new Date().getTimezoneOffset(),
    userAgent: navigator.userAgent || "",
  });

  const sendPayload = (payload, { preferBeacon = false } = {}) => {
    if (!endpointReady) {
      warn("Tracking endpoint not configured. Event stored locally:", payload);
      return;
    }

    const json = JSON.stringify(payload);
    if (preferBeacon && navigator.sendBeacon) {
      try {
        const blob = new Blob([json], { type: "application/json" });
        const result = navigator.sendBeacon(endpoint, blob);
        if (result) {
          log("Sent via sendBeacon", payload.event);
          return;
        }
      } catch (error) {
        warn("sendBeacon failed, falling back to fetch", error);
      }
    }

    fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: json,
      keepalive: true,
      mode: "cors",
    }).catch((error) => {
      warn("Tracking request failed", error);
    });
  };

  const dispatchEvent = (eventName, data = {}, options = {}) => {
    if (!eventName) return;
    const payload = Object.assign(basePayload(), {
      event: eventName,
      data,
    });
    sendPayload(payload, options);
  };

  const trackerApi = {
    track(eventName, data = {}, options = {}) {
      dispatchEvent(eventName, data, options);
    },
    getVisitorId() {
      return visitorId;
    },
    getSessionId() {
      return sessionId;
    },
  };

  global.hvhTracker = Object.assign({}, global.hvhTracker || {}, trackerApi);

  const pageStart = performance.now();
  const startTimestamp = Date.now();
  let activeSince = document.hidden ? null : performance.now();
  let engagedTime = 0;
  let closed = false;

  const syncActiveTime = () => {
    if (document.hidden) {
      if (activeSince !== null) {
        engagedTime += performance.now() - activeSince;
        activeSince = null;
      }
    } else {
      if (activeSince === null) {
        activeSince = performance.now();
      }
    }
  };

  document.addEventListener(
    "visibilitychange",
    () => {
      syncActiveTime();
      if (!document.hidden) {
        // When returning to the page, refresh referrer to current page
        pageContext.referrer = location.href;
      }
    },
    true
  );

  const finalizeSession = (reason) => {
    if (closed) return;
    syncActiveTime();
    const totalTime = performance.now() - pageStart;
    const activeTime = engagedTime;
    closed = true;

    dispatchEvent(
      "page_close",
      {
        reason,
        totalTimeMs: Math.round(totalTime),
        engagedTimeMs: Math.round(activeTime),
        totalTimeSeconds: +(totalTime / 1000).toFixed(2),
        engagedTimeSeconds: +(activeTime / 1000).toFixed(2),
        sessionDurationMs: Date.now() - startTimestamp,
      },
      { preferBeacon: true }
    );
  };

  window.addEventListener(
    "pagehide",
    (event) => {
      finalizeSession(event.persisted ? "pagehide-persisted" : "pagehide");
    },
    { once: true }
  );

  window.addEventListener(
    "beforeunload",
    () => {
      finalizeSession("beforeunload");
    },
    { once: true }
  );

  window.addEventListener(
    "unload",
    () => {
      finalizeSession("unload");
    },
    { once: true }
  );

  const pageViewData = {
    path: pageContext.path,
    referrer: pageContext.referrer,
    title: document.title,
  };

  dispatchEvent("page_view", pageViewData);
  log("Page view recorded");

  const resolveLabel = (element) => {
    if (!element) return "";
    if (element.dataset && element.dataset.trackLabel) {
      return element.dataset.trackLabel;
    }
    if ("value" in element && element.value) {
      return String(element.value).trim().slice(0, 120);
    }
    const text = element.textContent || "";
    return text.trim().slice(0, 120);
  };

  document.addEventListener(
    "click",
    (event) => {
      const target = event.target;
      if (!target) return;
      const actionable = target.closest
        ? target.closest("a, button, [data-track-click]")
        : null;
      if (!actionable) return;

      const dataset = actionable.dataset || {};
      if (dataset.trackIgnore === "true") return;

      const eventName = dataset.trackEvent || "interaction";
      const href = actionable.getAttribute ? actionable.getAttribute("href") : null;
      const isExternal =
        href && /^https?:\/\//i.test(href) && !href.includes(location.hostname);

      dispatchEvent(eventName, {
        targetTag: actionable.tagName,
        id: actionable.id || "",
        className: actionable.className || "",
        href,
        text: resolveLabel(actionable),
        external: Boolean(isExternal),
        x: event.clientX,
        y: event.clientY,
      });
    },
    true
  );

  document.addEventListener(
    "submit",
    (event) => {
      const form = event.target;
      if (!form || !form.tagName || form.tagName.toLowerCase() !== "form") {
        return;
      }

      const dataset = form.dataset || {};
      if (dataset.trackIgnore === "true") return;

      dispatchEvent(dataset.trackEvent || "form_submit", {
        id: form.id || "",
        name: form.getAttribute("name") || "",
        action: form.getAttribute("action") || "",
        method: (form.getAttribute("method") || "GET").toUpperCase(),
      });
    },
    true
  );

  log("HVH custom tracker ready", {
    endpointReady,
    siteId,
    visitorId,
    sessionId,
  });
})();
