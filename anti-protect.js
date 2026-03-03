(function () {
  if (window.hvhAntiProtectLoaded) return;
  window.hvhAntiProtectLoaded = true;

  const BLOCK_KEY = "hvh_perma_404_lock";
  const SCREENSHOT_STRIKE_KEY = "hvh_capture_strikes";
  const INSULT_STRIKE_KEY = "hvh_insult_strikes";
  const BLOCK_PATH = "/404.html";
  const CAPTURE_ARM_WINDOW_MS = 5000;
  const QUICK_HIDE_LOCK_MS = 1400;
  const AGGRESSIVE_BLUR_LOCK = false;
  const STARTUP_GRACE_MS = 2500;
  const SCREENSHOT_EVENT_COOLDOWN_MS = 1200;
  const ALERT_REDIRECT_DELAY_MS = 1100;
  const scriptStartedAt = Date.now();
  const is404Page = /\/404\.html$/i.test(window.location.pathname);
  const searchParams = new URLSearchParams(window.location.search);
  const shouldUnlock = searchParams.get("hvh_unlock") === "1";

  if (shouldUnlock) {
    try {
      window.localStorage.removeItem(BLOCK_KEY);
      window.localStorage.removeItem(SCREENSHOT_STRIKE_KEY);
      window.localStorage.removeItem(INSULT_STRIKE_KEY);
    } catch (_error) {}
  }

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

  const readStrikeCount = (key) => {
    try {
      const raw = window.localStorage.getItem(key);
      const value = Number(raw);
      return Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
    } catch (_error) {
      return 0;
    }
  };

  const writeStrikeCount = (key, value) => {
    try {
      window.localStorage.setItem(key, String(value));
    } catch (_error) {}
  };

  let isCaptureRedirectPending = false;
  const showViolentAlertAndRedirect = (message, permanent) => {
    if (is404Page) {
      if (permanent) {
        lockTo404Forever();
      }
      return;
    }

    if (isCaptureRedirectPending) return;
    isCaptureRedirectPending = true;

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
      navigator.vibrate([120, 50, 120, 50, 120, 50, 120]);
    }

    window.setTimeout(() => {
      if (permanent) {
        lockTo404Forever();
      } else {
        redirectTo404();
      }
    }, ALERT_REDIRECT_DELAY_MS);
  };

  let lastViolationEventAt = 0;
  const registerTwoStrikeViolation = (strikeKey, firstMessage, secondMessage) => {
    const now = Date.now();
    if (now - lastViolationEventAt <= SCREENSHOT_EVENT_COOLDOWN_MS) {
      return;
    }
    lastViolationEventAt = now;

    const strikes = readStrikeCount(strikeKey) + 1;
    writeStrikeCount(strikeKey, strikes);

    if (strikes >= 2) {
      showViolentAlertAndRedirect(secondMessage, true);
      return;
    }

    showViolentAlertAndRedirect(firstMessage, false);
  };

  const registerScreenshotViolation = () => {
    registerTwoStrikeViolation(
      SCREENSHOT_STRIKE_KEY,
      "SCREEN CAPTURE DETECTED. REDIRECTING TO 404.",
      "SCREEN CAPTURE DETECTED AGAIN. ACCESS PERMANENTLY BLOCKED."
    );
  };

  const registerInsultViolation = () => {
    registerTwoStrikeViolation(
      INSULT_STRIKE_KEY,
      "INSULT DETECTED. REDIRECTING TO 404.",
      "INSULT DETECTED AGAIN. ACCESS PERMANENTLY BLOCKED."
    );
  };

  window.hvhHandleInsultViolation = registerInsultViolation;

  const canAggressiveLock = () => Date.now() - scriptStartedAt >= STARTUP_GRACE_MS;

  if (isLocked && !is404Page) {
    window.location.replace(BLOCK_PATH);
    return;
  }

  let captureArmedAt = 0;
  const armCaptureLock = () => {
    captureArmedAt = Date.now();
  };
  const isCaptureArmed = () => Date.now() - captureArmedAt <= CAPTURE_ARM_WINDOW_MS;

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
        const screenshotCombo =
          key === "printscreen" ||
          (event.metaKey && event.shiftKey && key === "s") ||
          (event.shiftKey && key === "printscreen");
        const blocked =
          (ctrlOrMeta && ["s", "u", "p"].includes(key)) ||
          (ctrlOrMeta && event.shiftKey && ["i", "j", "c"].includes(key)) ||
          key === "f12" ||
          screenshotCombo;

        if (!blocked) return;
        event.preventDefault();
        event.stopPropagation();
        if (screenshotCombo) {
          armCaptureLock();
          registerScreenshotViolation();
          return;
        }
        redirectTo404();
      },
      true
    );

    document.addEventListener(
      "keyup",
      (event) => {
        const key = event.key.toLowerCase();
        if (key !== "printscreen") return;
        armCaptureLock();
        registerScreenshotViolation();
      },
      true
    );
  })();

  (function setupScreenCaptureDeterrents() {
    const layer = document.createElement("div");
    layer.id = "hvh-watermark-layer";
    layer.setAttribute("aria-hidden", "true");
    layer.style.cssText =
      "position:fixed;inset:0;pointer-events:none;z-index:2147483646;overflow:hidden;";

    const now = new Date();
    const stamp = now.toISOString().replace("T", " ").slice(0, 19);
    const label = (window.location.hostname || "protected") + " | " + stamp;

    for (let i = 0; i < 16; i++) {
      const mark = document.createElement("span");
      mark.textContent = label;
      mark.style.cssText =
        "position:absolute;color:rgba(255,255,255,0.16);font:700 14px/1 Arial,sans-serif;transform:rotate(-28deg);white-space:nowrap;letter-spacing:1px;text-shadow:0 0 2px rgba(0,0,0,0.4);";
      const x = (i % 4) * 25 + 2;
      const y = Math.floor(i / 4) * 25 + 6;
      mark.style.left = x + "%";
      mark.style.top = y + "%";
      layer.appendChild(mark);
    }

    const flash = document.createElement("div");
    flash.id = "hvh-capture-flash";
    flash.setAttribute("aria-hidden", "true");
    flash.style.cssText =
      "position:fixed;inset:0;pointer-events:none;z-index:2147483647;background:#000;opacity:0;transition:opacity 120ms ease;";

    document.addEventListener(
      "keydown",
      (event) => {
        const key = event.key.toLowerCase();
        const screenshotCombo =
          key === "printscreen" ||
          (event.metaKey && event.shiftKey && key === "s") ||
          (event.shiftKey && key === "printscreen");
        if (!screenshotCombo) return;
        flash.style.opacity = "0.78";
        window.setTimeout(() => {
          flash.style.opacity = "0";
        }, 140);
        armCaptureLock();
        registerScreenshotViolation();
      },
      true
    );

    let hiddenAt = 0;
    document.addEventListener(
      "visibilitychange",
      () => {
        if (AGGRESSIVE_BLUR_LOCK && document.hidden && canAggressiveLock()) {
          lockTo404Forever();
          return;
        }

        if (document.hidden) {
          hiddenAt = Date.now();
          return;
        }
        if (!hiddenAt) return;
        const elapsed = Date.now() - hiddenAt;
        hiddenAt = 0;
        if (elapsed <= QUICK_HIDE_LOCK_MS && isCaptureArmed()) {
          registerScreenshotViolation();
        }
      },
      true
    );

    let blurredAt = 0;
    window.addEventListener(
      "blur",
      () => {
        if (AGGRESSIVE_BLUR_LOCK && canAggressiveLock()) {
          lockTo404Forever();
          return;
        }
        blurredAt = Date.now();
      },
      true
    );
    window.addEventListener(
      "focus",
      () => {
        if (!blurredAt) return;
        const elapsed = Date.now() - blurredAt;
        blurredAt = 0;
        if (elapsed <= QUICK_HIDE_LOCK_MS && isCaptureArmed()) {
          registerScreenshotViolation();
        }
      },
      true
    );

    window.addEventListener(
      "pagehide",
      () => {
        if (AGGRESSIVE_BLUR_LOCK && canAggressiveLock()) {
          lockTo404Forever();
        }
      },
      true
    );

    if (document.body) {
      document.body.appendChild(layer);
      document.body.appendChild(flash);
    } else {
      window.addEventListener("DOMContentLoaded", () => {
        document.body.appendChild(layer);
        document.body.appendChild(flash);
      });
    }
  })();
})();
