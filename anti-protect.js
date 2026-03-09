(function () {
  if (window.hvhAntiProtectLoaded) return;
  window.hvhAntiProtectLoaded = true;

  const BLOCK_KEY = "hvh_perma_404_lock";
  const INSULT_STRIKE_KEY = "hvh_insult_strikes";
  const BLOCK_PATH = "/404.html";
  const ALERT_REDIRECT_DELAY_MS = 1200;
  const ALERT_DISMISS_DELAY_MS = 1700;
  const INSULT_NAG_INTERVAL_MS = 120;
  const INSULT_NAG_TEXT = "RESPECTFUL LANGUAGE ONLY.\nGO BACK NOW.";
  const ENFORCE_INSULT_LOCK = false;
  const ENFORCE_SERVER_BLACKLIST = false;
  const is404Page = /\/404\.html$/i.test(window.location.pathname);
  const searchParams = new URLSearchParams(window.location.search);
  const shouldUnlock = searchParams.get("hvh_unlock") === "1";

  if (shouldUnlock) {
    try {
      window.localStorage.removeItem(BLOCK_KEY);
      window.localStorage.removeItem(INSULT_STRIKE_KEY);
    } catch (_error) {}
  }

  // Emergency safety: clear historical locks so visitors can re-enter.
  try {
    window.localStorage.removeItem(BLOCK_KEY);
  } catch (_error) {}

  const isLocked = (() => {
    try {
      return window.localStorage.getItem(BLOCK_KEY) === "1";
    } catch (_error) {
      return false;
    }
  })();

  const redirectTo404 = () => {
    if (!is404Page) {
      window.location.replace(BLOCK_PATH);
    }
  };

  const lockTo404Forever = () => {
    try {
      window.localStorage.setItem(BLOCK_KEY, "1");
    } catch (_error) {}
    redirectTo404();
  };

  let isCaptureRedirectPending = false;
  let insultNagActive = false;
  let insultNagTimer = null;

  const stopInsultNagLoop = () => {
    insultNagActive = false;
    if (insultNagTimer) {
      window.clearTimeout(insultNagTimer);
      insultNagTimer = null;
    }
  };

  const startInsultNagLoop = (message = INSULT_NAG_TEXT) => {
    if (is404Page || insultNagActive) return;
    insultNagActive = true;
    try {
      window.history.pushState({ hvhInsultNag: Date.now() }, "", window.location.href);
    } catch (_error) {}

    const loopAlert = () => {
      if (!insultNagActive) return;
      try {
        window.alert(message);
      } catch (_error) {}
      if (navigator.vibrate) {
        try {
          navigator.vibrate([180, 60, 180, 60, 180]);
        } catch (_error) {}
      }
      insultNagTimer = window.setTimeout(loopAlert, INSULT_NAG_INTERVAL_MS);
    };

    loopAlert();
  };

  window.addEventListener("popstate", () => {
    stopInsultNagLoop();
  });

  window.addEventListener("pagehide", () => {
    stopInsultNagLoop();
  });

  const triggerViolentScreenShake = () => {
    const styleId = "hvh-violent-page-shake-style";
    if (!document.getElementById(styleId)) {
      const style = document.createElement("style");
      style.id = styleId;
      style.textContent =
        "@keyframes hvhPageViolentShake{0%{transform:translate(0,0) rotate(0deg);}10%{transform:translate(-14px,8px) rotate(-1.4deg);}20%{transform:translate(14px,-8px) rotate(1.4deg);}30%{transform:translate(-12px,7px) rotate(-1.2deg);}40%{transform:translate(12px,-7px) rotate(1.2deg);}50%{transform:translate(-10px,6px) rotate(-1deg);}60%{transform:translate(10px,-6px) rotate(1deg);}70%{transform:translate(-8px,5px) rotate(-0.8deg);}80%{transform:translate(8px,-5px) rotate(0.8deg);}90%{transform:translate(-6px,3px) rotate(-0.4deg);}100%{transform:translate(0,0) rotate(0deg);}}";
      document.head.appendChild(style);
    }

    const root = document.documentElement;
    const body = document.body;
    if (root) {
      root.style.animation = "hvhPageViolentShake 0.18s linear 7";
    }
    if (body) {
      body.style.animation = "hvhPageViolentShake 0.16s linear 8";
    }
  };

  const showViolentAlert = (
    message,
    {
      redirect = false,
      permanent = false,
    } = {}
  ) => {
    if (is404Page) {
      if (permanent) {
        lockTo404Forever();
      }
      return;
    }

    if (isCaptureRedirectPending) return;
    isCaptureRedirectPending = true;
    triggerViolentScreenShake();

    const styleId = "hvh-capture-alert-style";
    if (!document.getElementById(styleId)) {
      const style = document.createElement("style");
      style.id = styleId;
      style.textContent =
        "@keyframes hvhViolentShake{0%{transform:translate(-50%,-50%) translate(0,0) rotate(0deg);}10%{transform:translate(-50%,-50%) translate(-16px,7px) rotate(-2deg);}20%{transform:translate(-50%,-50%) translate(15px,-6px) rotate(2deg);}30%{transform:translate(-50%,-50%) translate(-14px,6px) rotate(-2deg);}40%{transform:translate(-50%,-50%) translate(14px,-6px) rotate(2deg);}50%{transform:translate(-50%,-50%) translate(-12px,6px) rotate(-1.5deg);}60%{transform:translate(-50%,-50%) translate(12px,-6px) rotate(1.5deg);}70%{transform:translate(-50%,-50%) translate(-10px,5px) rotate(-1deg);}80%{transform:translate(-50%,-50%) translate(10px,-5px) rotate(1deg);}90%{transform:translate(-50%,-50%) translate(-6px,3px) rotate(-0.5deg);}100%{transform:translate(-50%,-50%) translate(0,0) rotate(0deg);}}";
      document.head.appendChild(style);
    }

    const overlay = document.createElement("div");
    overlay.setAttribute("aria-hidden", "true");
    overlay.style.cssText =
      "position:fixed;inset:0;z-index:2147483647;background:rgba(10,0,0,0.88);pointer-events:none;";

    const popup = document.createElement("div");
    popup.style.cssText =
      "position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:min(86vw,540px);padding:20px 18px;background:#140005;border:2px solid #ff1f5a;border-radius:16px;color:#fff;font:700 22px/1.25 Arial,sans-serif;letter-spacing:1px;text-transform:uppercase;text-align:center;box-shadow:0 0 30px rgba(255,0,76,0.75);animation:hvhViolentShake 0.22s linear 5;";
    popup.textContent = message;

    overlay.appendChild(popup);
    if (document.body) {
      document.body.appendChild(overlay);
    } else {
      document.documentElement.appendChild(overlay);
    }

    if (navigator.vibrate) {
      navigator.vibrate([140, 40, 140, 40, 140, 40, 140, 40, 140]);
    }

    const cleanupOverlay = () => {
      try {
        overlay.remove();
      } catch (_error) {}
      try {
        const root = document.documentElement;
        const body = document.body;
        if (root) root.style.animation = "";
        if (body) body.style.animation = "";
      } catch (_error) {}
      isCaptureRedirectPending = false;
    };

    if (!redirect) {
      window.setTimeout(cleanupOverlay, ALERT_DISMISS_DELAY_MS);
      return;
    }

    window.setTimeout(() => {
      isCaptureRedirectPending = false;
      if (permanent) {
        lockTo404Forever();
      } else {
        redirectTo404();
      }
    }, ALERT_REDIRECT_DELAY_MS);
  };

  const reportInsultToServer = (details) => {
    const payload = {
      type: "insult",
      page: window.location.href,
      path: window.location.pathname,
      text: String(details?.text || "").slice(0, 280),
      context: String(details?.context || "").slice(0, 80),
      ts: new Date().toISOString(),
    };

    try {
      fetch("/booking/api/insult-log.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        credentials: "same-origin",
        keepalive: true,
        cache: "no-store",
        body: JSON.stringify(payload),
      }).catch(() => {});
    } catch (_error) {}
  };

  const registerInsultViolation = (details = {}) => {
    reportInsultToServer(details);
    if (ENFORCE_INSULT_LOCK) {
      showViolentAlert("INSULT DETECTED. IP PERMANENTLY BLOCKED.", {
        redirect: true,
        permanent: true,
      });
      return;
    }
    showViolentAlert("RESPECTFUL LANGUAGE ONLY.", { redirect: false });
    startInsultNagLoop();
  };

  window.hvhHandleInsultViolation = registerInsultViolation;

  if (isLocked && !is404Page) {
    window.location.replace(BLOCK_PATH);
    return;
  }

  (function enforceServerIpBlacklist() {
    if (!ENFORCE_SERVER_BLACKLIST) {
      return;
    }
    try {
      fetch("/booking/api/blacklist-check.php", {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
        headers: { Accept: "application/json" },
      })
        .then((response) => {
          if (!response.ok) return null;
          return response.json().catch(() => null);
        })
        .then((payload) => {
          if (!payload || payload.blocked !== true) return;
          showViolentAlert("IP BLOCKED. ACCESS DENIED.", {
            redirect: true,
            permanent: true,
          });
        })
        .catch(() => {});
    } catch (_error) {}
  })();

  (function setupAntiScriptCaptcha() {
    const MIN_FILL_TIME_MS = 3500;
    const targets = [
      {
        formId: "booking-form",
        statusId: "booking-status",
        fieldName: "booking_human_check",
      },
      {
        formId: "inline-newsletter-form",
        statusId: "inline-newsletter-status",
        fieldName: "newsletter_human_check",
      },
    ];

    const buildChallenge = () => {
      const left = Math.floor(Math.random() * 8) + 2;
      const right = Math.floor(Math.random() * 8) + 1;
      return {
        prompt: "Human check: " + left + " + " + right + " = ?",
        answer: String(left + right),
      };
    };

    const setStatus = (el, message) => {
      if (!el) return;
      el.textContent = message;
      el.dataset.level = "error";
    };

    targets.forEach(function (target) {
      const form = document.getElementById(target.formId);
      if (!form || form.dataset.hvhHumanCheckReady === "true") return;

      const statusEl = document.getElementById(target.statusId);
      const wrapper = document.createElement("div");
      wrapper.className = "contact-field anti-script-check";

      const label = document.createElement("label");
      const input = document.createElement("input");
      const inputId = target.formId + "-human-check";

      input.id = inputId;
      input.name = target.fieldName;
      input.type = "text";
      input.inputMode = "numeric";
      input.autocomplete = "off";
      input.className = "contact-input";
      input.placeholder = "answer";
      input.required = true;
      input.setAttribute("aria-required", "true");

      const timeField = document.createElement("input");
      timeField.type = "hidden";
      timeField.name = target.fieldName + "_started";

      wrapper.appendChild(label);
      wrapper.appendChild(input);

      const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
      if (submitButton) {
        form.insertBefore(wrapper, submitButton);
        form.insertBefore(timeField, submitButton);
      } else {
        form.appendChild(wrapper);
        form.appendChild(timeField);
      }

      let currentChallenge = null;
      const refreshChallenge = () => {
        currentChallenge = buildChallenge();
        label.setAttribute("for", inputId);
        label.textContent = currentChallenge.prompt + "*";
        input.value = "";
        timeField.value = String(Date.now());
      };

      refreshChallenge();
      form.dataset.hvhHumanCheckReady = "true";

      form.addEventListener(
        "submit",
        function (event) {
          const startedAt = Number(timeField.value || Date.now());
          const elapsed = Date.now() - startedAt;
          const answer = input.value.trim();

          if (elapsed < MIN_FILL_TIME_MS) {
            event.preventDefault();
            event.stopImmediatePropagation();
            setStatus(statusEl, "Too fast. Please wait a few seconds and try again.");
            refreshChallenge();
            input.focus();
            return;
          }

          if (!currentChallenge || answer !== currentChallenge.answer) {
            event.preventDefault();
            event.stopImmediatePropagation();
            setStatus(statusEl, "Wrong human check answer. Please try again.");
            refreshChallenge();
            input.focus();
          }
        },
        true
      );
    });
  })();

  (function setupAntiDownloadDeterrents() {
    const mediaSelector = "img, video, canvas, picture";
    const mediaWrapperSelector = ".embed-frame, .photos-grid, .service-card";

    const style = document.createElement("style");
    style.textContent =
      "img,video,canvas,picture{-webkit-user-drag:none;user-select:none;-webkit-user-select:none;}";
    document.head.appendChild(style);

    const protectElement = (element) => {
      if (!(element instanceof Element)) return;
      if (element.matches(mediaSelector)) {
        element.setAttribute("draggable", "false");
      }
      element.querySelectorAll(mediaSelector).forEach((child) => {
        child.setAttribute("draggable", "false");
      });
    };

    document.querySelectorAll(mediaSelector).forEach((item) => protectElement(item));

    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => protectElement(node));
      });
    });

    if (document.body) {
      observer.observe(document.body, { childList: true, subtree: true });
    }

    document.addEventListener(
      "contextmenu",
      (event) => {
        const target = event.target;
        if (
          target instanceof Element &&
          (target.closest(mediaSelector) || target.closest(mediaWrapperSelector))
        ) {
          event.preventDefault();
          redirectTo404();
        }
      },
      true
    );

    document.addEventListener(
      "dragstart",
      (event) => {
        const target = event.target;
        if (target instanceof Element && target.closest(mediaSelector)) {
          event.preventDefault();
          redirectTo404();
        }
      },
      true
    );

    document.addEventListener(
      "keydown",
      (event) => {
        const key = event.key.toLowerCase();
        const ctrlOrMeta = event.ctrlKey || event.metaKey;
        const blocked =
          (ctrlOrMeta && ["s", "u", "p"].includes(key)) ||
          (ctrlOrMeta && event.shiftKey && ["i", "j", "c"].includes(key)) ||
          key === "f12";

        if (!blocked) return;
        event.preventDefault();
        event.stopPropagation();
        redirectTo404();
      },
      true
    );
  })();
})();
