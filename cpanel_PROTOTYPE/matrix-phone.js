(function () {
  if (typeof window === "undefined" || window.hvhPhoneMatrixLoaded) {
    return;
  }
  window.hvhPhoneMatrixLoaded = true;

  const PHONE_NUMBER = "+15146076253";
  const PHONE_NUMBER_DISPLAY = "+1 514 607 6253";
  const SPACE_TRIGGER_COUNT = 3;
  const TAP_TRIGGER_COUNT = 5;
  const TRIGGER_WINDOW = 1200;
  const RAIN_DURATION = 3000;
  const ICONS = ["TEL", "PHN", "RNG", "DIA", "666", "CAL"];
  const ICON_FALL_SPEED = 0.35;
  const ENABLE_TOUCH_TRIGGER = false;
  const ENABLE_KEY_TRIGGER = false;
  const DEFAULT_LOCKED_MESSAGE =
    "OH-HO! YOU DIDN'T FILL OUT THE PASSCODE! THIS SHOWS ME YOU DIDN'T READ ANYTHING... pay attention! SMS or WhatsApp: +1 514 607 6253.";
  const XP_ERROR_AUDIO_URL = "https://www.myinstants.com/media/sounds/windows-error-sound-effect.mp3";

  let spaceCounter = 0;
  let tapCounter = 0;
  let lastSpaceTime = 0;
  let lastTapTime = 0;
  let rainTimeoutId = null;
  let animationId = null;
  let resizeHandler = null;
  let overlayCleanup = null;
  let phoneOverlay = null;
  let phoneButton = null;
  let phoneTag = null;
  let phoneOptions = null;
  let styleInjected = false;
  let allowPhoneButton = true;
  let sequenceResolve = null;
  let xpErrorAudio = null;

  const getLockedMessage = () => {
    const base = window.hvhPhoneLockedWarningMessage || DEFAULT_LOCKED_MESSAGE;
    if (base.includes(PHONE_NUMBER_DISPLAY)) {
      return base;
    }
    return `${base} SMS or WhatsApp: ${PHONE_NUMBER_DISPLAY}.`;
  };

  const playWarningSound = () => {
    try {
      if (!xpErrorAudio) {
        xpErrorAudio = new Audio(XP_ERROR_AUDIO_URL);
        xpErrorAudio.preload = "auto";
      }
      xpErrorAudio.pause();
      xpErrorAudio.currentTime = 0;
      xpErrorAudio.play().catch(() => {});
    } catch (_error) {}
  };

  const handleWhatsapp = () => {
    closeOptions();
    playWarningSound();
    window.open(`https://wa.me/${PHONE_NUMBER.replace(/\D/g, "")}`, "_blank", "noopener");
  };

  const handleSms = () => {
    closeOptions();
    window.open(`sms:${PHONE_NUMBER.replace(/\D/g, "")}`, "_self");
  };

  const ensureStyles = () => {
    if (styleInjected) return;
    styleInjected = true;
    const style = document.createElement("style");
    style.id = "hvh-phone-styles";
    style.textContent = `
      .hvh-phone-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 5, 3, 0.7);
        overflow: hidden;
        z-index: 9998;
        pointer-events: none;
      }
      .hvh-phone-overlay canvas {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        display: block;
      }
      .hvh-phone-icon {
        position: absolute;
        top: -10%;
        color: rgba(255, 87, 174, 0.78);
        text-shadow: 0 0 14px rgba(255, 62, 155, 0.6);
        animation-name: hvh-phone-fall;
        animation-timing-function: linear;
        animation-iteration-count: 1;
      }
      @keyframes hvh-phone-fall {
        0% {
          transform: translateY(-20vh) rotate(0deg);
          opacity: 0;
        }
        10% {
          opacity: 1;
        }
        100% {
          transform: translateY(120vh) rotate(360deg);
          opacity: 0;
        }
      }
      .hvh-phone-fab {
        position: fixed;
        bottom: clamp(96px, 11vw, 152px);
        right: clamp(20px, 4vw, 36px);
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: radial-gradient(circle at 30% 30%, rgba(255, 187, 239, 0.96), rgba(255, 36, 146, 0.95));
        box-shadow: 0 18px 38px rgba(255, 36, 146, 0.38), 0 0 28px rgba(255, 90, 185, 0.62);
        color: #36001c;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        border: 1px solid rgba(255, 132, 212, 0.65);
        cursor: pointer;
        opacity: 0;
        transform: translateY(20px) scale(0.9);
        transition: opacity 0.25s ease, transform 0.25s ease;
        z-index: 9999;
      }
      .hvh-phone-fab.visible {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
      .hvh-phone-tag {
        position: fixed;
        bottom: clamp(104px, 11.5vw, 160px);
        right: calc(clamp(20px, 4vw, 36px) + 78px);
        padding: 8px 14px;
        border-radius: 999px;
        background: linear-gradient(120deg, rgba(255, 210, 240, 0.95), rgba(255, 36, 146, 0.95));
        border: 1px solid rgba(255, 132, 212, 0.65);
        color: #2b0014;
        font: 700 13px/1.1 "JetBrains Mono", "Fira Code", Menlo, Consolas, ui-monospace, monospace;
        letter-spacing: 2px;
        text-transform: uppercase;
        box-shadow: 0 14px 30px rgba(255, 36, 146, 0.32), 0 0 18px rgba(255, 90, 185, 0.45);
        opacity: 0;
        transform: translateY(8px);
        transition: opacity 0.25s ease, transform 0.25s ease;
        z-index: 9999;
        pointer-events: none;
      }
      .hvh-phone-tag.visible {
        opacity: 1;
        transform: translateY(0);
      }
      .hvh-phone-fab svg {
        width: 36px;
        height: 36px;
        overflow: visible;
      }
      .hvh-phone-fab svg .hvh-phone-bloom {
        filter: drop-shadow(0 0 18px rgba(255, 62, 155, 0.78));
      }
      .hvh-phone-fab svg .hvh-phone-handset {
        fill: url(#hvh-phone-handset-grad);
        filter: drop-shadow(0 5px 12px rgba(140, 5, 60, 0.4));
      }
      .hvh-phone-fab svg .hvh-phone-chime {
        fill: none;
        stroke: rgba(255, 244, 250, 0.92);
        stroke-width: 1.7;
        stroke-linecap: round;
        opacity: 0;
        transform-origin: 16px 16px;
        animation: hvh-phone-chime 1.9s ease-in-out infinite;
      }
      .hvh-phone-fab svg .hvh-phone-chime.chime-2 {
        animation-delay: 0.3s;
      }
      @keyframes hvh-phone-chime {
        0% {
          opacity: 0;
          transform: scale(0.8);
        }
        35% {
          opacity: 0.65;
        }
        55% {
          opacity: 0.9;
        }
        100% {
          opacity: 0;
          transform: scale(1.1);
        }
      }
      .hvh-phone-options {
        position: fixed;
        bottom: calc(clamp(96px, 11vw, 152px) + 80px);
        right: clamp(20px, 4vw, 36px);
        display: grid;
        gap: 10px;
        padding: 14px 18px;
        border-radius: 16px;
        background: rgba(33, 0, 20, 0.92);
        border: 1px solid rgba(255, 126, 208, 0.35);
        box-shadow: 0 20px 40px rgba(255, 62, 155, 0.32);
        opacity: 0;
        transform: translateY(10px);
        pointer-events: none;
        transition: opacity 0.25s ease, transform 0.25s ease;
        z-index: 9999;
      }
      .hvh-phone-options.visible {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
      }
      .hvh-phone-options button {
        appearance: none;
        border: 1px solid rgba(255, 132, 212, 0.45);
        background: rgba(255, 132, 212, 0.12);
        color: rgba(255, 245, 250, 0.92);
        padding: 10px 18px;
        text-transform: uppercase;
        letter-spacing: 3px;
        font: 12px/1.2 "JetBrains Mono", "Fira Code", Menlo, Consolas, ui-monospace, monospace;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
      }
      .hvh-phone-options button:hover,
      .hvh-phone-options button:focus-visible {
        transform: translateY(-1px);
        background: rgba(255, 132, 212, 0.22);
        box-shadow: 0 12px 24px rgba(255, 62, 155, 0.28);
        outline: none;
      }
    `;
    document.head.appendChild(style);
  };

  const createOverlay = (duration = RAIN_DURATION) => {
    ensureStyles();

    if (overlayCleanup) {
      overlayCleanup(false);
    }

    const overlay = document.createElement("div");
    overlay.className = "hvh-phone-overlay";

    const canvas = document.createElement("canvas");
    overlay.appendChild(canvas);
    document.body.appendChild(overlay);

    phoneOverlay = overlay;

    const ctx = canvas.getContext("2d");
    let fontSize = 34;
    let columns = 0;
    let drops = [];
    let speeds = [];

    const GLYPHS =
      "01!@#$%^&*()_+-=[]{};:,.?/~<>ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

    const initCanvas = () => {
      const width = window.innerWidth;
      const height = window.innerHeight;
      canvas.width = width;
      canvas.height = height;
      fontSize = Math.max(26, Math.floor(width / 40));
      columns = Math.max(1, Math.floor(width / (fontSize * 0.8)));
      drops = new Array(columns).fill(0).map(() => Math.random() * -50);
      speeds = drops.map(() => 0.6 + Math.random() * 0.9);
      ctx.font = `${fontSize}px "JetBrains Mono","Fira Code",monospace`;
    };

    initCanvas();

    resizeHandler = initCanvas;
    window.addEventListener("resize", resizeHandler);

    const startTime = performance.now();
    const endTime = startTime + duration;

    const render = (timestamp) => {
      ctx.fillStyle = "rgba(0, 0, 0, 0.7)";
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      for (let i = 0; i < columns; i += 1) {
        const baseX = i * fontSize * 0.65;
        const baseY = drops[i] * fontSize;
        const trailLength = 16;

        for (let j = 0; j < trailLength; j += 1) {
          const glyph = GLYPHS[Math.floor(Math.random() * GLYPHS.length)];
          const y = baseY - j * fontSize;
          if (y < 0) continue;
          const intensity = 1 - j / trailLength;
          const hue = 140 + Math.random() * 10;
          ctx.fillStyle = `hsla(${hue}, 100%, ${50 + intensity * 25}%, ${0.25 + intensity * 0.75})`;
          ctx.fillText(glyph, baseX, y);
        }

        drops[i] += speeds[i];
        if (baseY - trailLength * fontSize > canvas.height) {
          drops[i] = Math.random() * -20;
          speeds[i] = 0.6 + Math.random() * 1.2;
        }
      }

      if (timestamp < endTime) {
        animationId = requestAnimationFrame(render);
      } else if (overlayCleanup) {
        overlayCleanup(true);
      }
    };

    animationId = requestAnimationFrame(render);

    overlayCleanup = (showButton = false) => {
      if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
      }
      if (resizeHandler) {
        window.removeEventListener("resize", resizeHandler);
        resizeHandler = null;
      }
      if (rainTimeoutId) {
        window.clearTimeout(rainTimeoutId);
        rainTimeoutId = null;
      }
      if (phoneOverlay && phoneOverlay.parentElement) {
        phoneOverlay.remove();
      }
      phoneOverlay = null;
      overlayCleanup = null;
      if (sequenceResolve) {
        sequenceResolve();
        sequenceResolve = null;
      }
      if (showButton && allowPhoneButton) {
        showPhoneButton();
      }
      allowPhoneButton = true;
    };

    rainTimeoutId = window.setTimeout(() => {
      if (overlayCleanup) {
        overlayCleanup(true);
      }
    }, duration + 100);
  };

  const toggleOptions = () => {
    if (!phoneOptions) return;
    phoneOptions.classList.toggle("visible");
  };

  const closeOptions = () => {
    if (phoneOptions) {
      phoneOptions.classList.remove("visible");
    }
  };

  const showPhoneButton = () => {
    if (document.body && document.body.classList.contains("age-gate")) {
      return;
    }
    ensureStyles();

    if (!phoneButton) {
      phoneButton = document.createElement("button");
      phoneButton.type = "button";
      phoneButton.className = "hvh-phone-fab";
      phoneButton.setAttribute("aria-label", "Contact Heidi");
      phoneButton.innerHTML = `
        <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
          <defs>
            <radialGradient id="hvh-phone-bloom" cx="50%" cy="50%" r="55%">
              <stop offset="0%" stop-color="#ffb2ef" />
              <stop offset="100%" stop-color="#ff1f8f" />
            </radialGradient>
            <linearGradient id="hvh-phone-handset-grad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#ffe3f9" />
              <stop offset="100%" stop-color="#ffffff" />
            </linearGradient>
          </defs>
          <circle class="hvh-phone-bloom" cx="16" cy="16" r="14" fill="url(#hvh-phone-bloom)" />
          <path
            class="hvh-phone-handset"
            d="M7.3 3.8c-1.2-1.2-3-1.2-4.1 0L1 6c-1.5 1.6-.8 4.6 1.6 7.8 3 4.3 8.7 9.9 13 13 3.2 2.4 6.2 3.1 7.8 1.6l2.1-2.1c1.2-1.2 1.2-3 0-4.1l-4.5-3.4c-.8-.6-2-.5-2.7.3l-1.4 1.3c-1.4-.8-3.5-2.7-4.4-4.4l1.3-1.4c.8-.8.9-2 .3-2.7l-3.4-4.5z"
            fill="url(#hvh-phone-handset-grad)"
          />
          <path class="hvh-phone-chime chime-1" d="M22.5 11.5c1.8 1.9 1.8 4.9 0 6.8" />
          <path class="hvh-phone-chime chime-2" d="M24.8 9.2c3 3.1 3 8.1 0 11.2" />
        </svg>`;
      phoneButton.addEventListener("click", (event) => {
        event.stopPropagation();
        if (!window.hvhPhoneUnlocked) {
          closeOptions();
          const message = getLockedMessage();
          playWarningSound();
          if (typeof window.hvhPhoneLockedWarning === "function") {
            window.hvhPhoneLockedWarning(message);
          } else {
            alert(message);
          }
          return;
        }
        toggleOptions();
      });

      document.body.appendChild(phoneButton);

      if (!phoneTag) {
        phoneTag = document.createElement("div");
        phoneTag.className = "hvh-phone-tag";
        phoneTag.textContent = "TEXT!";
        document.body.appendChild(phoneTag);
      }

      phoneOptions = document.createElement("div");
      phoneOptions.className = "hvh-phone-options";

      const waBtn = document.createElement("button");
      waBtn.type = "button";
      waBtn.textContent = `whatsapp ${PHONE_NUMBER_DISPLAY}`;
      waBtn.addEventListener("click", (event) => {
        event.stopPropagation();
        handleWhatsapp();
      });

      const smsBtn = document.createElement("button");
      smsBtn.type = "button";
      smsBtn.textContent = `sms ${PHONE_NUMBER_DISPLAY}`;
      smsBtn.addEventListener("click", (event) => {
        event.stopPropagation();
        handleSms();
      });

      const bookBtn = document.createElement("button");
      bookBtn.type = "button";
      bookBtn.textContent = "book online!";
      bookBtn.addEventListener("click", (event) => {
        event.stopPropagation();
        window.open("/booking/", "_blank", "noopener,noreferrer");
      });

      phoneOptions.appendChild(waBtn);
      phoneOptions.appendChild(smsBtn);
      phoneOptions.appendChild(bookBtn);
      document.body.appendChild(phoneOptions);

      document.addEventListener("click", (event) => {
        if (!phoneOptions) return;
        const isInside =
          phoneOptions.contains(event.target) || (phoneButton && phoneButton.contains(event.target));
        if (!isInside) {
          closeOptions();
        }
      });
    }

    requestAnimationFrame(() => {
      phoneButton.classList.add("visible");
      if (phoneTag) {
        phoneTag.classList.add("visible");
      }
    });
  };

  const triggerMatrixSequence = (duration = RAIN_DURATION) => {
    if (rainTimeoutId) {
      return;
    }
    createOverlay(duration);
  };

  const resetSpace = (now) => {
    spaceCounter = 0;
    lastSpaceTime = now;
  };

  const resetTap = (now) => {
    tapCounter = 0;
    lastTapTime = now;
  };

  const handleSpace = (event) => {
    if (event.defaultPrevented) return;
    if (event.key !== " " && event.code !== "Space") return;

    const target = event.target;
    if (
      target &&
      (target.isContentEditable ||
        target.tagName === "INPUT" ||
        target.tagName === "TEXTAREA" ||
        target.tagName === "SELECT")
    ) {
      return;
    }

    const now = Date.now();
    if (now - lastSpaceTime > TRIGGER_WINDOW) {
      spaceCounter = 0;
    }

    spaceCounter += 1;
    lastSpaceTime = now;

    if (spaceCounter >= SPACE_TRIGGER_COUNT) {
      event.preventDefault();
      triggerMatrixSequence();
      spaceCounter = 0;
    }
  };

  const handlePointer = (event) => {
    if (event.pointerType !== "touch") {
      return;
    }

    const now = Date.now();
    if (now - lastTapTime > TRIGGER_WINDOW) {
      tapCounter = 0;
    }

    tapCounter += 1;
    lastTapTime = now;

    if (tapCounter >= TAP_TRIGGER_COUNT) {
      triggerMatrixSequence();
      tapCounter = 0;
    }
  };

  const handleLegacyTouch = () => {
    const now = Date.now();
    if (now - lastTapTime > TRIGGER_WINDOW) {
      tapCounter = 0;
    }

    tapCounter += 1;
    lastTapTime = now;

    if (tapCounter >= TAP_TRIGGER_COUNT) {
      triggerMatrixSequence();
      tapCounter = 0;
    }
  };

  const cleanupOverlayOnNavigate = () => {
    if (overlayCleanup) {
      overlayCleanup(false);
    } else {
      if (phoneOverlay) {
        phoneOverlay.remove();
        phoneOverlay = null;
      }
      if (rainTimeoutId) {
        window.clearTimeout(rainTimeoutId);
        rainTimeoutId = null;
      }
    }
  };

  window.addEventListener("pagehide", cleanupOverlayOnNavigate);

  const initStyles = () => {
    ensureStyles();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStyles);
  } else {
    initStyles();
  }

  const hidePhoneButton = () => {
    if (phoneButton) {
      phoneButton.classList.remove("visible");
    }
    if (phoneTag) {
      phoneTag.classList.remove("visible");
    }
    if (phoneOptions) {
      phoneOptions.classList.remove("visible");
    }
  };

  const startSequence = (options = {}) => {
    const { showButtonAfter = true, duration } = options;

    if (rainTimeoutId) {
      allowPhoneButton = true;
      return Promise.resolve();
    }

    allowPhoneButton = showButtonAfter;
    hidePhoneButton();
    const sequenceDuration = typeof duration === "number" && duration > 0 ? duration : RAIN_DURATION;
    triggerMatrixSequence(sequenceDuration);

    return new Promise((resolve) => {
      sequenceResolve = () => {
        resolve();
        sequenceResolve = null;
      };
    });
  };

  window.hvhPhoneMatrix = {
    startSequence,
    showButton: () => {
      allowPhoneButton = true;
      showPhoneButton();
    },
    hideButton: hidePhoneButton,
    RAIN_DURATION,
  };
})();









