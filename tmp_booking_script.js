
      const form = document.getElementById("bookingForm");
      const durationSelect = document.getElementById("duration");
      const experienceSelect = document.getElementById("experience");
      const currencySelect = document.getElementById("currency");
      const citySelect = document.getElementById("city");
      const cityMismatchWarning = document.getElementById("cityMismatchWarning");
      const preferredDate = document.getElementById("preferred_date");
      const preferredTime = document.getElementById("preferred_time");
      const nameInput = document.getElementById("name");
      const phoneInput = document.getElementById("phone");
      const notesInput = document.getElementById("notes");
      const whatsappDiscuss = document.getElementById("whatsappDiscuss");
      const durationHint = document.getElementById("durationHint");
      const touringDayCityEl = document.getElementById("touringDayCity");
      const availabilityNote = document.getElementById("availabilityNote");
      const availabilitySummary = document.getElementById("availabilitySummary");
      const dayCalendar = document.getElementById("dayCalendar");
      const panelRequest = document.getElementById("panelRequest");
      const panelAvailability = document.getElementById("panelAvailability");
      const panelPayment = document.getElementById("panelPayment");
      const panelSubmit = document.getElementById("panelSubmit");
      const paymentMethodField = document.getElementById("paymentMethodField");
      const depositConsentRow = document.getElementById("depositConsentRow");
      const paymentCryptoHint = document.getElementById("paymentCryptoHint");
      const paymentGrid = document.getElementById("paymentGrid");
      const outcallField = document.getElementById("outcallAddressField");
      const bookingTypeField = document.getElementById("bookingTypeField");
      const outcallInput = document.getElementById("outcall_address");
      const statusEl = document.getElementById("formStatus");
      const submitBtn = document.getElementById("submitBtn");
      const paymentMethod = document.getElementById("payment_method");
      const depositConfirm = document.getElementById("deposit_confirm");
      const depositBox = document.getElementById("depositBox");
      const baseRateDisplay = document.getElementById("baseRateDisplay");
      const totalRateDisplay = document.getElementById("totalRateDisplay");
      const pseAddonRow = document.getElementById("pseAddonRow");
      const pseAddonDisplay = document.getElementById("pseAddonDisplay");
      const depositRateDisplay = document.getElementById("depositRateDisplay");
      const totalRateNote = document.getElementById("totalRateNote");
      const depositRateNote = document.getElementById("depositRateNote");
      const tourCityEl = document.getElementById("tourCity");
      const tourTzEl = document.getElementById("tourTz");
      const successPopup = document.getElementById("successPopup");
      const successTitle = document.getElementById("successTitle");
      const successMessage = document.getElementById("successMessage");
      const successEmail = document.getElementById("successEmail");
      const successLink = document.getElementById("successLink");
      const successClose = document.getElementById("successClose");
      const alertPopup = document.getElementById("alertPopup");
      const alertPopupTitle = document.getElementById("alertPopupTitle");
      const alertPopupMessage = document.getElementById("alertPopupMessage");
      const alertPopupClose = document.getElementById("alertPopupClose");
      const emailInput = document.getElementById("email");
      const wizardTop = document.getElementById("wizardTop");
      const wizardCount = document.getElementById("wizardCount");
      const wizardTitle = document.getElementById("wizardTitle");
      const wizardStatus = document.getElementById("wizardStatus");
      const wizardFill = document.getElementById("wizardFill");
      const wizardNext = document.getElementById("wizardNext");
      const wizardBack = document.getElementById("wizardBack");
      const wizardNav = document.getElementById("wizardNav");
      const languageButtons = document.querySelectorAll(".age-language [data-language-choice]");
      const LANGUAGE_KEY = "hvh_inside_language";
      const SUPPORTED_LANGUAGES = ["en", "fr"];
      let currentLanguage = "en";
      const I18N = {
        en: {
          back_to_inside: "Back to inside",
          booking_title: "Van Booking Portal",
          booking_subtitle:
            "Simple, private scheduling. Send your request and I email the payment details. Your booking is confirmed after payment is processed.",
          wizard_step_label: "Booking step",
          wizard_count: "Step {current} of {total}",
          request_title: "Booking request",
          label_name: "Name",
          label_phone: "Phone number",
          phone_placeholder: "+ (country code) 555 1234",
          followup_phone_title: "Would you like me to contact you about my next trip to this city by phone?",
          followup_email_title: "Would you like me to contact you about my next trip to this city by email?",
          yes: "Yes",
          no: "No",
          other_cities: "Other cities?",
          followup_phone_placeholder: "Optional: Toronto, Vancouver, Paris",
          label_email: "Email",
          followup_email_placeholder: "Optional: Montreal, London, Berlin",
          label_notes: "Requests, info, tell me about you if you wish :)",
          notes_placeholder: "Optional. Share requests or any helpful info.",
          notes_hint: "Optional. Keep it respectful.",
          label_date: "Date of booking",
          touring_day_prefix: "On that day, Heidi will be in:",
          select_date: "Select a date",
          label_city: "City of booking",
          choose_option: "choose option",
          label_currency: "Currency",
          label_duration: "Duration",
          duration_hint: "Rates update with currency. Crypto shown in CAD.",
          discuss_whatsapp: "Discuss it with me on WhatsApp",
          label_service: "Service",
          label_location: "Location",
          incall: "Incall",
          outcall: "Outcall",
          outcall_hint: "Outcall requires an address so I can confirm travel time.",
          outcall_address: "Outcall address",
          availability_title: "Daily availability",
          availability_note_default: "Pick a date and duration to see available start times in order.",
          timezone_hint_prefix: "Times shown in this city timezone:",
          selected_time: "Selected time",
          payment_title: "Payment confirmation",
          base_rate: "Base rate:",
          pse_addon: "PSE add-on:",
          total_rate: "Total:",
          deposit_rate: "20% deposit:",
          deposit_note: "Deposit is 20% of total.",
          label_payment_method: "Payment method",
          etransfer_canada: "e-Transfer (Canada only)",
          deposit_consent: "I confirm the 20% deposit and payment method are correct.",
          crypto_hint: "Crypto amounts are shown as CAD reference.",
          request_booking: "Request booking",
          back: "Back",
          next: "Next",
          success_title: "Request received",
          pay_now: "Pay now",
          got_it: "Got it",
          alert_title: "Attention",
          alert_ok: "OK",
          booking_unavailable: "Booking is unavailable.",
          bad_vibe_alert: ":( bad vibe alert!",
          review_step: "Please review this step and try again.",
          payment_email_prefix: "Payment email:",
          select_duration: "Select duration",
          discuss_whatsapp_short: "Discuss on WhatsApp",
          custom_24h_note: "Custom 24+ hour bookings are confirmed after WhatsApp discussion.",
          custom_24h_validity: "Please discuss 24+ hour bookings on WhatsApp.",
          deposit_percent_note: "Deposit is {percent}% of total.",
          crypto_reference_note: "Crypto reference shown in CAD.",
          city_no_tour: "No touring in this city/date. Read touring dates carefully or arrange Fly me to you.",
          city_date_mismatch: "Heidi is in {city} on {date}. Read touring carefully or arrange Fly me to you.",
          availability_pick_date: "Pick a date to view availability.",
          city_tba: "TBA",
          availability_selected_date: "Availability shown for the selected date.",
          availability_need_standard:
            "Select a standard duration to view available times. 24+ hours is discussed on WhatsApp.",
          availability_read_touring: "Read touring dates carefully, or arrange Fly me to you.",
          availability_past_date: "This date is in the past. Pick a future date.",
          availability_none_left: "No start times left for this date and duration.",
          step_where_booking: "Where are you booking?",
          step_currency: "Which currency?",
          step_date: "What date do you want?",
          step_type: "Incall or outcall?",
          step_duration: "How long?",
          step_service: "Which service?",
          step_pick_time: "Pick an available time",
          step_name: "What name should I use?",
          step_phone: "What phone number can I reach?",
          step_email: "What email should receive payment details?",
          step_notes: "Requests, info, tell me about you if you wish :)",
          step_payment_method: "How will you send deposit?",
          step_deposit_confirm: "Confirm the deposit terms",
          step_submit: "Send your booking request",
          complete_step: "Please complete: {step}.",
          success_request_sent: "Request sent. Your reference: {id}. Check your email to proceed with payment.",
          send_error_prefix: "Couldn't send that.",
          duration_30m: "30 minutes",
          duration_1h: "1 hour",
          duration_1_5h: "1.5 hours",
          duration_2h: "2 hours",
          duration_3h: "3 hours",
          duration_4h: "4 hours",
          duration_social: "Social experiment (3 hours)",
          duration_8_12h: "8-12 hours",
          duration_24p: "24+ hours",
        },
        fr: {
          back_to_inside: "Retour a inside",
          booking_title: "Portail de reservation Van",
          booking_subtitle:
            "Reservation simple et privee. Envoyez votre demande et je vous envoie les details de paiement par email. La reservation est confirmee apres validation du paiement.",
          wizard_step_label: "Etape de reservation",
          wizard_count: "Etape {current} sur {total}",
          request_title: "Demande de reservation",
          label_name: "Nom",
          label_phone: "Numero de telephone",
          phone_placeholder: "+ (indicatif pays) 555 1234",
          followup_phone_title:
            "Souhaitez-vous que je vous contacte pour mon prochain passage dans cette ville par telephone?",
          followup_email_title:
            "Souhaitez-vous que je vous contacte pour mon prochain passage dans cette ville par email?",
          yes: "Oui",
          no: "Non",
          other_cities: "Autres villes?",
          followup_phone_placeholder: "Optionnel: Toronto, Vancouver, Paris",
          label_email: "Email",
          followup_email_placeholder: "Optionnel: Montreal, Londres, Berlin",
          label_notes: "Demandes, infos, parlez-moi de vous si vous voulez :)",
          notes_placeholder: "Optionnel. Partagez vos demandes ou infos utiles.",
          notes_hint: "Optionnel. Restez respectueux.",
          label_date: "Date de reservation",
          touring_day_prefix: "Ce jour-la, Heidi sera a:",
          select_date: "Choisissez une date",
          label_city: "Ville de reservation",
          choose_option: "choisir une option",
          label_currency: "Devise",
          label_duration: "Duree",
          duration_hint: "Les tarifs changent avec la devise. Crypto affichee en CAD.",
          discuss_whatsapp: "Discuter avec moi sur WhatsApp",
          label_service: "Service",
          label_location: "Lieu",
          incall: "Incall",
          outcall: "Outcall",
          outcall_hint: "Outcall demande une adresse pour confirmer le deplacement.",
          outcall_address: "Adresse outcall",
          availability_title: "Disponibilites du jour",
          availability_note_default: "Choisissez date et duree pour voir les heures disponibles dans l'ordre.",
          timezone_hint_prefix: "Heures affichees dans le fuseau de cette ville:",
          selected_time: "Heure choisie",
          payment_title: "Confirmation de paiement",
          base_rate: "Tarif de base:",
          pse_addon: "Supplement PSE:",
          total_rate: "Total:",
          deposit_rate: "Acompte 20%:",
          deposit_note: "L'acompte est de 20% du total.",
          label_payment_method: "Methode de paiement",
          etransfer_canada: "e-Transfer (Canada seulement)",
          deposit_consent: "Je confirme que l'acompte de 20% et la methode de paiement sont corrects.",
          crypto_hint: "Les montants crypto sont affiches en reference CAD.",
          request_booking: "Envoyer la demande",
          back: "Retour",
          next: "Suivant",
          success_title: "Demande recue",
          pay_now: "Payer maintenant",
          got_it: "Compris",
          alert_title: "Attention",
          alert_ok: "OK",
          booking_unavailable: "Reservation indisponible.",
          bad_vibe_alert: ":( alerte bad vibe!",
          review_step: "Verifiez cette etape puis recommencez.",
          payment_email_prefix: "Email paiement:",
          select_duration: "Choisissez la duree",
          discuss_whatsapp_short: "Discuter sur WhatsApp",
          custom_24h_note: "Les reservations 24h+ sont confirmees apres discussion WhatsApp.",
          custom_24h_validity: "Veuillez discuter des reservations 24h+ sur WhatsApp.",
          deposit_percent_note: "L'acompte est de {percent}% du total.",
          crypto_reference_note: "Reference crypto affichee en CAD.",
          city_no_tour: "Aucune tournee cette date dans cette ville. Lisez la tournee ou choisissez Fly me to you.",
          city_date_mismatch: "Heidi est a {city} le {date}. Lisez la tournee ou choisissez Fly me to you.",
          availability_pick_date: "Choisissez une date pour voir les disponibilites.",
          city_tba: "A definir",
          availability_selected_date: "Disponibilites pour la date choisie.",
          availability_need_standard:
            "Choisissez une duree standard pour voir les heures disponibles. Le 24h+ se discute sur WhatsApp.",
          availability_read_touring: "Lisez les dates de tournee, ou choisissez Fly me to you.",
          availability_past_date: "Cette date est passee. Choisissez une date future.",
          availability_none_left: "Aucune heure de depart restante pour cette date et duree.",
          step_where_booking: "Ou reservez-vous?",
          step_currency: "Quelle devise?",
          step_date: "Quelle date voulez-vous?",
          step_type: "Incall ou outcall?",
          step_duration: "Quelle duree?",
          step_service: "Quel service?",
          step_pick_time: "Choisissez une heure disponible",
          step_name: "Quel nom dois-je utiliser?",
          step_phone: "Quel numero pour vous joindre?",
          step_email: "Quel email doit recevoir les details de paiement?",
          step_notes: "Demandes, infos, parlez-moi de vous si vous voulez :)",
          step_payment_method: "Comment enverrez-vous l'acompte?",
          step_deposit_confirm: "Confirmez les conditions d'acompte",
          step_submit: "Envoyer votre demande",
          complete_step: "Veuillez completer: {step}.",
          success_request_sent: "Demande envoyee. Votre reference: {id}. Verifiez votre email pour proceder au paiement.",
          send_error_prefix: "Impossible d'envoyer.",
          duration_30m: "30 minutes",
          duration_1h: "1 heure",
          duration_1_5h: "1,5 heure",
          duration_2h: "2 heures",
          duration_3h: "3 heures",
          duration_4h: "4 heures",
          duration_social: "Experience sociale (3 heures)",
          duration_8_12h: "8-12 heures",
          duration_24p: "24+ heures",
        },
      };
      const formatTemplate = (template, vars = {}) =>
        String(template || "").replace(/\{(\w+)\}/g, (_m, key) => (vars[key] ?? `{${key}}`));
      const t = (key, vars = {}) => {
        const pack = I18N[currentLanguage] || I18N.en;
        const fallback = I18N.en[key] || key;
        return formatTemplate(pack[key] || fallback, vars);
      };
      const getStoredLanguage = () => {
        try {
          const stored = window.localStorage.getItem(LANGUAGE_KEY);
          if (stored && SUPPORTED_LANGUAGES.includes(stored)) return stored;
        } catch (_error) {}
        return null;
      };
      const detectBrowserLanguage = () => {
        const langs = Array.isArray(navigator.languages) ? navigator.languages : [navigator.language || "en"];
        for (const lang of langs) {
          const short = String(lang || "").slice(0, 2).toLowerCase();
          if (SUPPORTED_LANGUAGES.includes(short)) return short;
        }
        return "en";
      };
      const CUSTOM_ALERT_SOUND = "/alert-badvibe.mp3";
      const FALLBACK_ALERT_SOUND = "/winxp-alert.mp3";
      const xpSound = new Audio(CUSTOM_ALERT_SOUND);
      xpSound.preload = "auto";
      xpSound.volume = 1;
      let alertSoundFallbackLoaded = false;
      xpSound.addEventListener(
        "error",
        () => {
          if (alertSoundFallbackLoaded) return;
          alertSoundFallbackLoaded = true;
          xpSound.src = FALLBACK_ALERT_SOUND;
          xpSound.load();
        },
        { once: true }
      );
      let bookingLocked = false;

      const PROFANITY_PATTERNS = [
        /\b(bitch|biatch|b!tch)\b/i,
        /\b(fuck|fuk|fck|f\*+k|motherf\w*)\b/i,
        /\b(slut|sl@t)\b/i,
        /\b(whore|hore)\b/i,
        /\b(cunt)\b/i,
        /\b(hoe|skank|thot)\b/i,
        /\b(stupid|idiot|dumb|moron|brainless|loser)\b/i,
        /\b(worthless|useless|trash|pathetic)\b/i,
        /\b(bimbo|dog|ugly)\b/i,
        /\b(salope|pute|putain|connard|connasse|encule|enculee|enfoire|merde)\b/i,
        /\b(puta|puto|cabron|pendejo|gilipollas|culero|pinche)\b/i,
        /\b(puttana|stronzo|stronza|bastardo)\b/i,
        /\b(vagabunda|otario|merda)\b/i,
        /\b(schlampe|fotze|arschloch|scheisse|dummkopf)\b/i,
        /\b(suka|blyat)\b/i,
      ];
      const setBookingLock = (message) => {
        bookingLocked = true;
        if (submitBtn) submitBtn.disabled = true;
        form.querySelectorAll("input, select, textarea, button").forEach((el) => {
          if (el.id === "successClose") return;
          el.disabled = true;
        });
        statusEl.textContent = message || t("booking_unavailable");
      };

      const checkBlacklist = async (email, phone) => {
        try {
          const response = await fetch("api/blacklist.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email, phone }),
          });
          const data = await response.json();
          return !!data.blocked;
        } catch (_error) {
          return false;
        }
      };

      const checkBlacklistOnLoad = async () => {
        try {
          const response = await fetch("api/blacklist.php", { cache: "no-store" });
          const data = await response.json();
          if (data && data.blocked) {
            setBookingLock(t("booking_unavailable"));
          }
        } catch (_error) {}
      };

      let tourCity = "Touring city not set";
      let tourTz = "America/Toronto";
      let bufferMinutes = 30;
      let selectedTourTimezone = tourTz;
      let selectedBufferMinutes = bufferMinutes;
      let availabilityMode = "open";
      let blockedSlots = [];
      let recurringBlocks = [];
      let citySchedules = [];
      let selectedSlotButton = null;
      let wizardStepIndex = 0;

        let tourSchedule = [
          { start: "2026-02-08", end: "2026-02-14", city: "Montreal" },
          { start: "2026-02-15", end: "2026-02-18", city: "Toronto" },
          { start: "2026-02-19", end: "2026-02-21", city: "Vancouver" },
          { start: "2026-02-22", end: "2026-03-04", city: "Montreal" },
          { start: "2026-03-05", end: "2026-03-09", city: "London (UK)" },
          { start: "2026-03-10", end: "2026-03-13", city: "Berlin" },
          { start: "2026-03-14", end: "2026-03-19", city: "Paris" },
        ];

      const monitoredFields = Array.from(
        form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], textarea')
      );
      const BASE_CURRENCY = "CAD";
      const DEPOSIT_PERCENT = 20;
      const PSE_BILL_ADDON = 100;
      const FIAT_CURRENCIES = ["CAD", "USD", "EUR", "GBP"];
      const CRYPTO_CURRENCIES = ["USDC", "BTC", "LTC"];
      const FX_OVERRIDES = {
        EUR: 0.7,
        GBP: 0.65,
      };
      const RATE_API = `https://api.exchangerate.host/latest?base=${BASE_CURRENCY}&symbols=${FIAT_CURRENCIES.join(",")}`;
      const currencyCache = { rates: null };
      const RATE_BRACKETS = [0.5, 1, 1.5, 2, 3, 4, 12];
      const RATE_TABLE = {
        gfe: {
          0.5: 400,
          1: 700,
          1.5: 1000,
          2: 1300,
          3: 1600,
          4: 2000,
          12: 3000,
        },
        pse: {
          0.5: 800,
          1: 800,
          1.5: 1100,
          2: 1400,
          3: 1600,
          4: 2000,
          12: 3000,
        },
        social: {
          3: 1000,
        },
      };
      const LONG_SESSION_MIN = 8;
      const LONG_SESSION_MAX = 12;
      const LONG_SESSION_RATE = 3000;
      const DURATION_OPTIONS = [
        { labelKey: "duration_30m", hours: 0.5, value: "0.5" },
        { labelKey: "duration_1h", hours: 1, value: "1" },
        { labelKey: "duration_1_5h", hours: 1.5, value: "1.5" },
        { labelKey: "duration_2h", hours: 2, value: "2" },
        { labelKey: "duration_3h", hours: 3, value: "3" },
        { labelKey: "duration_4h", hours: 4, value: "4" },
        { labelKey: "duration_social", hours: 3, value: "social", rateKey: "social" },
        { labelKey: "duration_8_12h", hours: 10, value: "8-12" },
        { labelKey: "duration_24p", hours: 0, value: "24+", custom: true },
      ];
      const getDurationLabel = (entry) => t(entry?.labelKey || "");

      const containsProfanity = (value) => PROFANITY_PATTERNS.some((pattern) => pattern.test(value));

      const playXpSound = () => {
        try {
          if (xpSound.src === "" || xpSound.src.endsWith("/")) {
            xpSound.src = FALLBACK_ALERT_SOUND;
          }
          xpSound.pause();
          xpSound.currentTime = 0;
          xpSound.play().catch(() => {
            try {
              if (xpSound.src.indexOf(FALLBACK_ALERT_SOUND) === -1) {
                xpSound.src = FALLBACK_ALERT_SOUND;
                xpSound.load();
                xpSound.play().catch(() => {});
              }
            } catch (_error) {}
          });
        } catch (_error) {}
      };

      const showBadVibe = (message = t("bad_vibe_alert")) => {
        const text = String(message || t("bad_vibe_alert"));
        statusEl.textContent = text;
        playXpSound();
        showAlertPopup(text);
      };

      const showWizardWarning = (message) => {
        const text = String(message || t("review_step"));
        if (wizardStatus) {
          wizardStatus.textContent = text;
        }
        showBadVibe(text);
      };

      const closeAlertPopup = () => {
        if (!alertPopup) return;
        alertPopup.classList.remove("show");
        alertPopup.setAttribute("aria-hidden", "true");
      };

      const showAlertPopup = (message) => {
        if (!alertPopup || !alertPopupMessage) return;
        alertPopupMessage.textContent = String(message || "");
        alertPopup.classList.add("show");
        alertPopup.setAttribute("aria-hidden", "false");
        const card = alertPopup.querySelector(".alert-popup-card");
        if (card) {
          card.classList.remove("shake");
          void card.offsetWidth;
          card.classList.add("shake");
        }
        if (alertPopupClose) {
          alertPopupClose.focus();
        }
      };

      const closeSuccessPopup = () => {
        if (!successPopup) return;
        successPopup.classList.remove("show");
        successPopup.setAttribute("aria-hidden", "true");
      };

      const showSuccessPopup = (message, link, emailValue) => {
        if (!successPopup || !successMessage) return;
        successMessage.textContent = message;
        if (successEmail) {
          if (emailValue) {
            successEmail.textContent = `${t("payment_email_prefix")} ${emailValue}`;
          } else {
            successEmail.textContent = "";
          }
        }
        successPopup.classList.add("show");
        successPopup.setAttribute("aria-hidden", "false");
        playXpSound();
        if (successLink) {
          if (link) {
            successLink.href = link;
            successLink.classList.remove("hidden");
          } else {
            successLink.classList.add("hidden");
          }
        }
        if (successClose) {
          successClose.focus();
        }
      };

      if (successClose) {
        successClose.addEventListener("click", closeSuccessPopup);
      }
      if (successPopup) {
        successPopup.addEventListener("click", (event) => {
          if (event.target === successPopup) {
            closeSuccessPopup();
          }
        });
      }
      if (alertPopupClose) {
        alertPopupClose.addEventListener("click", closeAlertPopup);
      }
      if (alertPopup) {
        alertPopup.addEventListener("click", (event) => {
          if (event.target === alertPopup) {
            closeAlertPopup();
          }
        });
      }
      document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") return;
        if (alertPopup && alertPopup.classList.contains("show")) {
          closeAlertPopup();
          return;
        }
        if (successPopup && successPopup.classList.contains("show")) {
          closeSuccessPopup();
        }
      });

      monitoredFields.forEach((field) => {
        field.dataset.cleanValue = field.value || "";
        field.addEventListener("input", () => {
          const currentValue = field.value;
          if (containsProfanity(currentValue)) {
            field.value = field.dataset.cleanValue || "";
            showBadVibe();
            field.blur();
            window.setTimeout(() => {
              try {
                field.focus({ preventScroll: false });
              } catch (_error) {
                field.focus();
              }
            }, 120);
            return;
          }
          field.dataset.cleanValue = currentValue;
        });
      });

      const detectDefaultCurrency = () => {
        try {
          const resolved = new Intl.NumberFormat().resolvedOptions().currency;
          if (FIAT_CURRENCIES.includes(resolved)) return resolved;
        } catch (_error) {
          return BASE_CURRENCY;
        }
        return BASE_CURRENCY;
      };

      const formatCurrency = (amount, currency) => {
        try {
          const decimals = currency === BASE_CURRENCY || currency === "USD" ? 0 : 2;
          return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency,
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
          }).format(amount);
        } catch (_error) {
          return `${currency} ${amount.toFixed(2)}`;
        }
      };

      const normalizeExperience = (value) => (value === "gfe" ? "gfe" : "pse");

      const getBaseRate = (hours, experience, rateKey) => {
        if (!Number.isFinite(hours) || hours <= 0) return 0;
        if (rateKey === "social") {
          return RATE_TABLE.social[3];
        }
        if (hours >= LONG_SESSION_MIN && hours <= LONG_SESSION_MAX) {
          return LONG_SESSION_RATE;
        }
        const expKey = normalizeExperience(experience);
        const rates = RATE_TABLE[expKey] || RATE_TABLE.gfe;
        if (rates[hours]) return rates[hours];
        let lower = null;
        let upper = null;
        const sorted = RATE_BRACKETS.slice().sort((a, b) => a - b);
        for (const bracket of sorted) {
          if (bracket < hours) {
            lower = bracket;
            continue;
          }
          if (bracket > hours) {
            upper = bracket;
            break;
          }
        }
        if (lower === null) return rates[sorted[0]] || 0;
        if (upper === null) return rates[sorted[sorted.length - 1]] || 0;
        const lowerRate = rates[lower];
        const upperRate = rates[upper];
        const ratio = (hours - lower) / (upper - lower);
        return Math.round(lowerRate + (upperRate - lowerRate) * ratio);
      };

      const getFiatRates = async () => {
        if (currencyCache.rates) return currencyCache.rates;
        try {
          const response = await fetch(RATE_API);
          if (!response.ok) throw new Error("Rate fetch failed");
          const data = await response.json();
          if (!data || !data.rates) throw new Error("Rate data missing");
          currencyCache.rates = data.rates;
          return data.rates;
        } catch (_error) {
          return null;
        }
      };

      const formatRateLabel = async (amount, currency) => {
        if (!currency || currency === BASE_CURRENCY) {
          return formatCurrency(amount, BASE_CURRENCY);
        }
        if (currency === "USD") {
          return formatCurrency(amount, "USD");
        }
        if (CRYPTO_CURRENCIES.includes(currency)) {
          return `${formatCurrency(amount, BASE_CURRENCY)} CAD`;
        }
        const overrideRate = FX_OVERRIDES[currency];
        if (typeof overrideRate === "number") {
          return formatCurrency(amount * overrideRate, currency);
        }
        const rates = await getFiatRates();
        const rate = rates ? rates[currency] : null;
        if (!rate) {
          return formatCurrency(amount, BASE_CURRENCY);
        }
        return formatCurrency(amount * rate, currency);
      };

      const updatePaymentSummary = async () => {
        if (!baseRateDisplay || !totalRateDisplay || !depositRateDisplay) return;
        const selected = durationSelect.selectedOptions[0];
        if (!selected) {
          baseRateDisplay.textContent = t("select_duration");
          totalRateDisplay.textContent = t("select_duration");
          depositRateDisplay.textContent = "--";
          if (pseAddonRow) pseAddonRow.classList.add("hidden");
          if (pseAddonDisplay) pseAddonDisplay.textContent = "--";
          if (totalRateNote) totalRateNote.textContent = "";
          if (depositRateNote) depositRateNote.textContent = t("deposit_note");
          return;
        }
        const hours = Number(selected.dataset.hours || 0);
        const rateKey = selected.dataset.rateKey || "";
        const experience = experienceSelect.value || "gfe";
        const currency = currencySelect.value || BASE_CURRENCY;
        if (selected.dataset.custom === "true" || selected.value === "24+") {
          baseRateDisplay.textContent = t("discuss_whatsapp_short");
          totalRateDisplay.textContent = t("discuss_whatsapp_short");
          depositRateDisplay.textContent = "--";
          if (pseAddonRow) pseAddonRow.classList.add("hidden");
          if (pseAddonDisplay) pseAddonDisplay.textContent = "--";
          if (totalRateNote) totalRateNote.textContent = "";
          if (depositRateNote) {
            depositRateNote.textContent = t("custom_24h_note");
          }
          if (durationSelect) {
            durationSelect.setCustomValidity(t("custom_24h_validity"));
          }
          if (whatsappDiscuss) {
            whatsappDiscuss.hidden = false;
          }
          return;
        }
        if (durationSelect) {
          durationSelect.setCustomValidity("");
        }
        if (whatsappDiscuss) {
          whatsappDiscuss.hidden = true;
        }
        if (!Number.isFinite(hours) || hours <= 0) {
          baseRateDisplay.textContent = t("select_duration");
          totalRateDisplay.textContent = t("select_duration");
          depositRateDisplay.textContent = "--";
          if (pseAddonRow) pseAddonRow.classList.add("hidden");
          if (pseAddonDisplay) pseAddonDisplay.textContent = "--";
          if (totalRateNote) totalRateNote.textContent = "";
          if (depositRateNote) depositRateNote.textContent = t("deposit_percent_note", { percent: DEPOSIT_PERCENT });
          return;
        }
        const baseRate = getBaseRate(hours, experience, rateKey);
        const pseAddonBase = normalizeExperience(experience) === "pse" ? PSE_BILL_ADDON : 0;
        const totalBase = baseRate + pseAddonBase;
        const depositBase = Math.round(totalBase * (DEPOSIT_PERCENT / 100));
        const baseLabel = await formatRateLabel(baseRate, currency);
        const totalLabel = await formatRateLabel(totalBase, currency);
        const depositLabel = await formatRateLabel(depositBase, currency);
        baseRateDisplay.textContent = baseLabel;
        totalRateDisplay.textContent = totalLabel;
        depositRateDisplay.textContent = depositLabel;
        if (pseAddonRow && pseAddonDisplay) {
          if (pseAddonBase > 0) {
            const addonLabel = await formatRateLabel(pseAddonBase, currency);
            pseAddonDisplay.textContent = addonLabel;
            pseAddonRow.classList.remove("hidden");
          } else {
            pseAddonDisplay.textContent = "--";
            pseAddonRow.classList.add("hidden");
          }
        }
        if (totalRateNote) {
          totalRateNote.textContent = CRYPTO_CURRENCIES.includes(currency) ? t("crypto_reference_note") : "";
        }
        if (depositRateNote) {
          depositRateNote.textContent = t("deposit_percent_note", { percent: DEPOSIT_PERCENT });
        }
      };

      const updateDurationOptions = async () => {
        const currentCurrency = currencySelect.value || BASE_CURRENCY;
        const currentExperience = experienceSelect.value || "gfe";
        const previousValue = durationSelect.value;
        durationSelect.innerHTML = "";
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = t("choose_option");
        placeholder.disabled = true;
        placeholder.selected = true;
        durationSelect.appendChild(placeholder);

        for (const entry of DURATION_OPTIONS) {
          const option = document.createElement("option");
          option.value = entry.value;
          option.dataset.hours = String(entry.hours);
          option.dataset.rateKey = entry.rateKey || "";
          option.dataset.label = getDurationLabel(entry);
          if (entry.custom) {
            option.dataset.custom = "true";
            option.textContent = `${getDurationLabel(entry)} - ${t("discuss_whatsapp_short")}`;
          } else {
            const baseRate = getBaseRate(entry.hours, currentExperience, entry.rateKey || "");
            const priceLabel = await formatRateLabel(baseRate, currentCurrency);
            option.textContent = `${getDurationLabel(entry)} - ${priceLabel}`;
          }
          durationSelect.appendChild(option);
        }

        if (
          previousValue &&
          Array.from(durationSelect.options).some((option) => option.value === previousValue)
        ) {
          durationSelect.value = previousValue;
        } else {
          durationSelect.value = "";
        }
        await updatePaymentSummary();
        renderDayCalendar();
      };

        const normalizeTourSchedule = (list) => {
          if (!Array.isArray(list)) return [];
          return list
            .map((entry) => ({
              start: String(entry?.start || "").trim(),
              end: String(entry?.end || "").trim(),
              city: String(entry?.city || "").trim(),
            }))
            .filter(
              (entry) =>
                entry.start &&
                entry.end &&
                entry.city &&
                /^\d{4}-\d{2}-\d{2}$/.test(entry.start) &&
                /^\d{4}-\d{2}-\d{2}$/.test(entry.end) &&
                entry.start <= entry.end
            )
            .sort((a, b) => a.start.localeCompare(b.start));
        };

        const normalizeCityScheduleList = (list) => {
          if (!Array.isArray(list)) return [];
          return list
            .map((entry) => ({
              city: String(entry?.city || "").trim(),
              start: String(entry?.start || "").trim(),
              end: String(entry?.end || "").trim(),
              timezone: String(entry?.timezone || "").trim(),
              buffer_minutes: Number(entry?.buffer_minutes || 0),
            }))
            .filter(
              (entry) =>
                entry.city &&
                entry.start &&
                entry.end &&
                /^\d{4}-\d{2}-\d{2}$/.test(entry.start) &&
                /^\d{4}-\d{2}-\d{2}$/.test(entry.end) &&
                entry.start <= entry.end
            )
            .sort((a, b) => a.start.localeCompare(b.start));
        };

        const getCityForDate = (dateKey) => {
          if (!dateKey) return "";
          const match = tourSchedule.find((entry) => dateKey >= entry.start && dateKey <= entry.end);
          return match ? match.city : "";
        };

      const normalizeCityName = (value) =>
        String(value || "")
          .trim()
          .toLowerCase()
          .replace(/\s+/g, " ");

      const isFlyMeCity = (value) => normalizeCityName(value) === "fly me to you";

      const getCityScheduleForDate = (dateKey, selectedCity = "") => {
        if (!dateKey) return null;
        let targetCity = selectedCity;
        if (!targetCity || isFlyMeCity(targetCity)) {
          targetCity = getCityForDate(dateKey);
        }
        if (!targetCity) return null;
        const cityKey = normalizeCityName(targetCity);
        return (
          citySchedules.find(
            (entry) =>
              dateKey >= entry.start &&
              dateKey <= entry.end &&
              normalizeCityName(entry.city) === cityKey
          ) || null
        );
      };

      const updateSelectedTourContext = () => {
        const dateKey = preferredDate?.value || "";
        const cityValue = citySelect?.value || "";
        const schedule = getCityScheduleForDate(dateKey, cityValue);
        selectedTourTimezone = schedule?.timezone || tourTz;
        const scheduleBuffer = Number(schedule?.buffer_minutes);
        selectedBufferMinutes = Number.isFinite(scheduleBuffer) ? Math.max(0, scheduleBuffer) : bufferMinutes;
        if (tourTzEl) {
          tourTzEl.textContent = selectedTourTimezone;
        }
        const displayCity = schedule?.city || getCityForDate(dateKey) || tourCity;
        if (tourCityEl) {
          tourCityEl.textContent = displayCity;
        }
        return schedule;
      };

      const getNowInTourTimezone = () => {
        try {
          updateSelectedTourContext();
          const parts = new Intl.DateTimeFormat("en-CA", {
            timeZone: selectedTourTimezone || tourTz,
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hourCycle: "h23",
          }).formatToParts(new Date());
          const values = {};
          parts.forEach((part) => {
            if (part.type !== "literal") {
              values[part.type] = part.value;
            }
          });
          const dateKey = `${values.year}-${values.month}-${values.day}`;
          const minutes = Number(values.hour || 0) * 60 + Number(values.minute || 0);
          return { dateKey, minutes };
        } catch (_error) {
          const fallback = new Date();
          return {
            dateKey: fallback.toISOString().split("T")[0],
            minutes: fallback.getHours() * 60 + fallback.getMinutes(),
          };
        }
      };

      const setBookingDateFloor = () => {
        if (!preferredDate) return;
        const now = getNowInTourTimezone();
        preferredDate.min = now.dateKey;
      };

      const validateDateNotPast = () => {
        if (!preferredDate) return true;
        preferredDate.setCustomValidity("");
        if (!preferredDate.value) return true;
        const now = getNowInTourTimezone();
        if (preferredDate.value < now.dateKey) {
          preferredDate.setCustomValidity("Pick today or a future date.");
          return false;
        }
        return true;
      };

      const isSelectedDateTimeInPast = (dateKey, timeLabel) => {
        if (!dateKey || !timeLabel) return false;
        const minutes = timeToMinutes(timeLabel);
        if (minutes === null) return false;
        const now = getNowInTourTimezone();
        if (dateKey < now.dateKey) return true;
        if (dateKey > now.dateKey) return false;
        return minutes <= now.minutes;
      };

      const validateCityForDate = ({ showMessage = true } = {}) => {
        if (!citySelect || !preferredDate) return true;
        updateSelectedTourContext();
        citySelect.setCustomValidity("");
        if (!citySelect.value || !preferredDate.value) {
          if (cityMismatchWarning && showMessage) {
            cityMismatchWarning.classList.add("hidden");
          }
          return true;
        }
        if (isFlyMeCity(citySelect.value)) {
          if (cityMismatchWarning && showMessage) {
            cityMismatchWarning.classList.add("hidden");
          }
          return true;
        }
        const touringCity = getCityForDate(preferredDate.value);
        let message = "";
        if (!touringCity) {
          message = t("city_no_tour");
        } else if (normalizeCityName(citySelect.value) !== normalizeCityName(touringCity)) {
          message = t("city_date_mismatch", { city: touringCity, date: preferredDate.value });
        }
        if (message) {
          citySelect.setCustomValidity(message);
          if (cityMismatchWarning && showMessage) {
            cityMismatchWarning.textContent = message;
            cityMismatchWarning.classList.remove("hidden");
          }
          return false;
        }
        if (cityMismatchWarning && showMessage) {
          cityMismatchWarning.classList.add("hidden");
        }
        return true;
      };

      const validateSelectedTimeNotPast = () => {
        if (!preferredDate || !preferredTime) return true;
        if (!preferredDate.value || !preferredTime.value) return true;
        if (isSelectedDateTimeInPast(preferredDate.value, preferredTime.value)) {
          preferredTime.setCustomValidity("Please pick a future time.");
          return false;
        }
        preferredTime.setCustomValidity("");
        return true;
      };

      const timeToMinutes = (timeValue) => {
        const [hour, minute] = String(timeValue || "").split(":").map((value) => Number(value));
        if (Number.isNaN(hour) || Number.isNaN(minute)) return null;
        return hour * 60 + minute;
      };

      const weekdayLabelToIndex = (label) => {
        const map = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
        return map[label] ?? 0;
      };

      const getWeekdayIndex = (dateKey) => {
        const [year, month, day] = String(dateKey).split("-").map((value) => Number(value));
        if (!year || !month || !day) return 0;
        const base = new Date(Date.UTC(year, month - 1, day, 12, 0));
        const label = new Intl.DateTimeFormat("en-US", {
          timeZone: selectedTourTimezone || tourTz,
          weekday: "short",
        }).format(base);
        return weekdayLabelToIndex(label);
      };

      const isRecurringBlocked = (dateKey, windowStart, windowEnd) => {
        if (!Array.isArray(recurringBlocks) || !recurringBlocks.length) return false;
        const weekdayIndex = getWeekdayIndex(dateKey);
        return recurringBlocks.some((block) => {
          if (!block) return false;
          const days = Array.isArray(block.days) ? block.days : [];
          if (!days.includes(weekdayIndex)) return false;
          if (block.all_day) return true;
          const startMinutes = timeToMinutes(block.start);
          const endMinutes = timeToMinutes(block.end);
          if (startMinutes === null || endMinutes === null) return false;
          return windowStart < endMinutes && windowEnd > startMinutes;
        });
      };

      const isSlotBlocked = (dateKey, startMinutes, endMinutes) => {
        if (availabilityMode === "closed") return true;
        if (!Array.isArray(blockedSlots) || !blockedSlots.length) {
          return isRecurringBlocked(dateKey, startMinutes, endMinutes);
        }
        const activeBuffer = Number.isFinite(selectedBufferMinutes) ? selectedBufferMinutes : bufferMinutes;
        const windowStart = Math.max(0, startMinutes - activeBuffer);
        const windowEnd = Math.min(1440, endMinutes + activeBuffer);
        if (isRecurringBlocked(dateKey, windowStart, windowEnd)) {
          return true;
        }
        return blockedSlots.some((entry) => {
          if (!entry || entry.date !== dateKey) return false;
          const blockStart = timeToMinutes(entry.start);
          const blockEnd = timeToMinutes(entry.end);
          if (blockStart === null || blockEnd === null) return false;
          return windowStart < blockEnd && windowEnd > blockStart;
        });
      };

      const clearSelectedSlot = () => {
        if (selectedSlotButton) {
          selectedSlotButton.classList.remove("selected");
          selectedSlotButton = null;
        }
        if (preferredTime) {
          preferredTime.value = "";
          preferredTime.setCustomValidity("");
        }
      };

      const getSelectedDurationMinutes = () => {
        const selected = durationSelect?.selectedOptions?.[0];
        if (!selected) return null;
        if (selected.dataset.custom === "true" || selected.value === "24+") {
          return null;
        }
        const hours = Number(selected.dataset.hours || 0);
        if (!Number.isFinite(hours) || hours <= 0) {
          return null;
        }
        return Math.max(30, Math.round(hours * 60));
      };

      const toTimeLabel = (minutes) => {
        const safe = Math.max(0, Math.min(1439, Number(minutes) || 0));
        const hour = String(Math.floor(safe / 60)).padStart(2, "0");
        const minute = String(safe % 60).padStart(2, "0");
        return `${hour}:${minute}`;
      };

      const renderDayCalendar = () => {
        if (!dayCalendar || !availabilitySummary || !preferredDate) return;
        const dateKey = preferredDate.value;
        if (!dateKey) {
          dayCalendar.innerHTML = "";
          dayCalendar.dataset.city = "";
          availabilitySummary.textContent = "";
          if (touringDayCityEl) touringDayCityEl.textContent = t("select_date");
          availabilityNote.textContent = t("availability_pick_date");
          clearSelectedSlot();
          return;
        }

        const cityContext = updateSelectedTourContext();
        const dayCity = cityContext?.city || getCityForDate(dateKey) || tourCity;
        if (touringDayCityEl) touringDayCityEl.textContent = dayCity || t("city_tba");
        dayCalendar.dataset.city = dayCity || t("city_tba");
        availabilityNote.textContent = t("availability_selected_date");
        validateCityForDate({ showMessage: true });

        dayCalendar.innerHTML = "";
        clearSelectedSlot();
        const durationMinutes = getSelectedDurationMinutes();
        if (!durationMinutes) {
          const customMessage = document.createElement("p");
          customMessage.className = "day-empty";
          customMessage.textContent = t("availability_need_standard");
          dayCalendar.appendChild(customMessage);
          availabilitySummary.innerHTML = "";
          return;
        }

        if (!validateCityForDate({ showMessage: true })) {
          const cityMessage = document.createElement("p");
          cityMessage.className = "day-empty";
          cityMessage.textContent = t("availability_read_touring");
          dayCalendar.appendChild(cityMessage);
          availabilitySummary.innerHTML = "";
          return;
        }

        const now = getNowInTourTimezone();
        if (dateKey < now.dateKey) {
          const pastDateMessage = document.createElement("p");
          pastDateMessage.className = "day-empty";
          pastDateMessage.textContent = t("availability_past_date");
          dayCalendar.appendChild(pastDateMessage);
          availabilitySummary.innerHTML = "";
          return;
        }

        const slots = [];
        let blockedCount = 0;
        for (let startMinutes = 0; startMinutes + durationMinutes <= 1440; startMinutes += 30) {
          const endMinutes = startMinutes + durationMinutes;
          if (dateKey === now.dateKey && startMinutes <= now.minutes) {
            blockedCount += 1;
            continue;
          }
          const blocked = isSlotBlocked(dateKey, startMinutes, endMinutes);
          if (blocked) {
            blockedCount += 1;
            continue;
          }
          slots.push({
            startLabel: toTimeLabel(startMinutes),
            endLabel: toTimeLabel(endMinutes),
          });
        }

        if (!slots.length) {
          const emptyMessage = document.createElement("p");
          emptyMessage.className = "day-empty";
          emptyMessage.textContent = t("availability_none_left");
          dayCalendar.appendChild(emptyMessage);
        }

        slots.forEach((slot) => {
          const button = document.createElement("button");
          button.type = "button";
          button.className = "slot available";
          button.textContent = `${slot.startLabel} - ${slot.endLabel}`;
          button.addEventListener("click", () => {
            if (selectedSlotButton) {
              selectedSlotButton.classList.remove("selected");
            }
            selectedSlotButton = button;
            selectedSlotButton.classList.add("selected");
            if (preferredTime) {
              preferredTime.value = slot.startLabel;
              validateSelectedTimeNotPast();
            }
          });
          if (preferredTime && preferredTime.value && preferredTime.value === slot.startLabel) {
            selectedSlotButton = button;
            selectedSlotButton.classList.add("selected");
          }
          dayCalendar.appendChild(button);
        });

        availabilitySummary.innerHTML = "";
      };

      const loadTourSchedule = async () => {
        try {
          const response = await fetch("api/site-content.php", { cache: "no-store" });
          if (!response.ok) throw new Error("touring");
          const data = await response.json();
          const schedule = normalizeTourSchedule(data.touring || []);
          if (schedule.length) {
            tourSchedule = schedule;
            validateCityForDate({ showMessage: true });
            renderDayCalendar();
          }
        } catch (_error) {
        }
      };

      const toggleOutcall = () => {
        const selected = document.querySelector('input[name="booking_type"]:checked');
        const isOutcall = selected && selected.value === "outcall";
        if (outcallField) {
          outcallField.classList.toggle("hidden", !isOutcall);
        }
        if (outcallInput) {
          outcallInput.required = isOutcall;
          if (!isOutcall) outcallInput.value = "";
        }
      };

      const requestFields = Array.from(panelRequest?.querySelectorAll(".field") || []);
      const cityField = citySelect ? citySelect.closest(".field") : null;
      const dateField = preferredDate ? preferredDate.closest(".field") : null;
      const durationField = durationSelect ? durationSelect.closest(".field") : null;
      const experienceField = experienceSelect ? experienceSelect.closest(".field") : null;
      const currencyField = currencySelect ? currencySelect.closest(".field") : null;
      const nameField = nameInput ? nameInput.closest(".field") : null;
      const phoneField = phoneInput ? phoneInput.closest(".field") : null;
      const emailField = emailInput ? emailInput.closest(".field") : null;
      const notesField = notesInput ? notesInput.closest(".field") : null;
      const allPanels = [panelRequest, panelAvailability, panelPayment, panelSubmit].filter(Boolean);

      const wizardSteps = [
        {
          key: "city",
          titleKey: "step_where_booking",
          panel: panelRequest,
          requestFields: [cityField],
          validate: () => citySelect?.reportValidity() ?? true,
        },
        {
          key: "currency",
          titleKey: "step_currency",
          panel: panelRequest,
          requestFields: [currencyField],
          validate: () => currencySelect?.reportValidity() ?? true,
        },
        {
          key: "date",
          titleKey: "step_date",
          panel: panelRequest,
          requestFields: [dateField],
          validate: () => {
            setBookingDateFloor();
            validateDateNotPast();
            if (!(preferredDate?.reportValidity() ?? true)) return false;
            if (!validateCityForDate({ showMessage: true })) {
              citySelect?.reportValidity();
              return false;
            }
            return true;
          },
        },
        {
          key: "type",
          titleKey: "step_type",
          panel: panelRequest,
          requestFields: [bookingTypeField, outcallField],
          validate: () => {
            toggleOutcall();
            const selected = document.querySelector('input[name="booking_type"]:checked');
            if (!selected) return false;
            if (outcallInput?.required && !(outcallInput?.reportValidity() ?? true)) {
              return false;
            }
            return true;
          },
        },
        {
          key: "duration",
          titleKey: "step_duration",
          panel: panelRequest,
          requestFields: [durationField],
          validate: () => durationSelect?.reportValidity() ?? true,
        },
        {
          key: "service",
          titleKey: "step_service",
          panel: panelRequest,
          requestFields: [experienceField],
          validate: () => experienceSelect?.reportValidity() ?? true,
        },
        {
          key: "availability",
          titleKey: "step_pick_time",
          panel: panelAvailability,
          validate: () => {
            if (!(durationSelect?.reportValidity() ?? true)) {
              return false;
            }
            if (!validateDateNotPast()) {
              preferredDate?.reportValidity();
              return false;
            }
            if (!validateCityForDate({ showMessage: true })) {
              citySelect?.reportValidity();
              return false;
            }
            if (!(preferredTime?.reportValidity() ?? true)) {
              return false;
            }
            if (!validateSelectedTimeNotPast()) {
              preferredTime?.reportValidity();
              return false;
            }
            return true;
          },
        },
        {
          key: "name",
          titleKey: "step_name",
          panel: panelRequest,
          requestFields: [nameField],
          validate: () => nameInput?.reportValidity() ?? true,
        },
        {
          key: "phone",
          titleKey: "step_phone",
          panel: panelRequest,
          requestFields: [phoneField],
          validate: () => phoneInput?.reportValidity() ?? true,
        },
        {
          key: "email",
          titleKey: "step_email",
          panel: panelRequest,
          requestFields: [emailField],
          validate: () => emailInput?.reportValidity() ?? true,
        },
        {
          key: "notes",
          titleKey: "step_notes",
          panel: panelRequest,
          requestFields: [notesField],
          validate: () => true,
        },
        {
          key: "payment-method",
          titleKey: "step_payment_method",
          panel: panelPayment,
          paymentMode: "method",
          validate: () => paymentMethod?.reportValidity() ?? true,
        },
        {
          key: "deposit-confirm",
          titleKey: "step_deposit_confirm",
          panel: panelPayment,
          paymentMode: "confirm",
          validate: () => depositConfirm?.reportValidity() ?? true,
        },
        {
          key: "submit",
          titleKey: "step_submit",
          panel: panelSubmit,
          validate: () => true,
        },
      ];

      const setPaymentStepMode = (mode) => {
        if (!panelPayment) return;
        if (depositBox) {
          depositBox.classList.remove("wizard-hidden");
        }
        if (mode === "method") {
          paymentGrid?.classList.remove("wizard-hidden");
          depositConsentRow?.classList.add("wizard-hidden");
          paymentCryptoHint?.classList.add("wizard-hidden");
          return;
        }
        if (mode === "confirm") {
          paymentGrid?.classList.add("wizard-hidden");
          depositConsentRow?.classList.remove("wizard-hidden");
          paymentCryptoHint?.classList.remove("wizard-hidden");
          return;
        }
        paymentGrid?.classList.remove("wizard-hidden");
        depositConsentRow?.classList.remove("wizard-hidden");
        paymentCryptoHint?.classList.remove("wizard-hidden");
      };

      const renderWizardStep = (index) => {
        if (!wizardSteps.length) return;
        const safeIndex = Math.max(0, Math.min(wizardSteps.length - 1, index));
        wizardStepIndex = safeIndex;
        const step = wizardSteps[wizardStepIndex];
        const total = wizardSteps.length;

        allPanels.forEach((panel) => panel.classList.add("wizard-hidden"));
        if (step.panel) {
          step.panel.classList.remove("wizard-hidden");
        }

        requestFields.forEach((field) => field.classList.add("wizard-hidden"));
        if (step.requestFields?.length) {
          step.requestFields.forEach((field) => field?.classList.remove("wizard-hidden"));
        }
        if (step.key === "type") {
          toggleOutcall();
        }

        if (step.panel === panelPayment) {
          setPaymentStepMode(step.paymentMode || "all");
        } else {
          setPaymentStepMode("all");
        }

        if (wizardCount) {
          wizardCount.textContent = t("wizard_count", { current: wizardStepIndex + 1, total });
        }
        if (wizardTitle) {
          wizardTitle.textContent = t(step.titleKey || "wizard_step_label");
        }
        if (wizardFill) {
          wizardFill.style.width = `${Math.round(((wizardStepIndex + 1) / total) * 100)}%`;
        }
        if (wizardBack) {
          wizardBack.disabled = wizardStepIndex === 0;
        }
        if (wizardNext) {
          wizardNext.classList.toggle("wizard-hidden", wizardStepIndex === total - 1);
        }
        if (wizardNav) {
          wizardNav.classList.remove("wizard-hidden");
        }
        if (wizardTop) {
          wizardTop.classList.remove("wizard-hidden");
        }

        if (step.key === "availability") {
          renderDayCalendar();
        }
      };

      const validateCurrentWizardStep = () => {
        const step = wizardSteps[wizardStepIndex];
        if (!step || typeof step.validate !== "function") return true;
        return !!step.validate();
      };

      const getCurrentWizardWarningMessage = () => {
        const step = wizardSteps[wizardStepIndex];
        if (!step) {
          return t("review_step");
        }
        const controls = [
          citySelect,
          preferredDate,
          durationSelect,
          preferredTime,
          paymentMethod,
          depositConfirm,
          nameInput,
          phoneInput,
          emailInput,
          notesInput,
          outcallInput,
        ];
        for (const control of controls) {
          if (!control) continue;
          if (!control.checkValidity()) {
            return control.validationMessage || t("complete_step", { step: t(step.titleKey || "wizard_step_label") });
          }
        }
        return t("complete_step", { step: t(step.titleKey || "wizard_step_label") });
      };

      const nextWizardStep = () => {
        if (!validateCurrentWizardStep()) {
          showWizardWarning(getCurrentWizardWarningMessage());
          return;
        }
        renderWizardStep(wizardStepIndex + 1);
        window.scrollTo({ top: 0, behavior: "smooth" });
      };

      const prevWizardStep = () => {
        renderWizardStep(wizardStepIndex - 1);
        window.scrollTo({ top: 0, behavior: "smooth" });
      };

      const loadAvailability = async () => {
        try {
          const response = await fetch("api/availability.php");
          if (!response.ok) {
            throw new Error("Availability not available");
          }
          const data = await response.json();
          tourCity = data.tour_city || tourCity;
          tourTz = data.tour_timezone || tourTz;
          bufferMinutes = Number(data.buffer_minutes) || bufferMinutes;
          selectedTourTimezone = tourTz;
          selectedBufferMinutes = bufferMinutes;
          availabilityMode = data.availability_mode || availabilityMode;
          blockedSlots = Array.isArray(data.blocked) ? data.blocked : [];
          recurringBlocks = Array.isArray(data.recurring) ? data.recurring : [];
          citySchedules = normalizeCityScheduleList(data.city_schedules || []);
          updateSelectedTourContext();
          setBookingDateFloor();
          validateDateNotPast();
          validateCityForDate({ showMessage: true });
          renderDayCalendar();
        } catch (_error) {
          if (tourCityEl) tourCityEl.textContent = tourCity;
          if (tourTzEl) tourTzEl.textContent = selectedTourTimezone || tourTz;
        }
      };

      form.addEventListener("change", (event) => {
        if (event.target.name === "booking_type") {
          toggleOutcall();
        }
        if (event.target.id === "city") {
          validateCityForDate({ showMessage: true });
          renderDayCalendar();
        }
        if (event.target.id === "preferred_date") {
          validateDateNotPast();
          validateCityForDate({ showMessage: true });
          renderDayCalendar();
        }
        if (event.target.id === "currency" || event.target.id === "experience") {
          updateDurationOptions();
        }
        if (event.target.id === "duration") {
          updatePaymentSummary();
          renderDayCalendar();
        }
      });

      if (wizardNext) {
        wizardNext.addEventListener("click", nextWizardStep);
      }
      if (wizardBack) {
        wizardBack.addEventListener("click", prevWizardStep);
      }

      form.addEventListener("submit", async (event) => {
        event.preventDefault();
        statusEl.textContent = "";

        const lastStepIndex = wizardSteps.length - 1;
        if (wizardStepIndex !== lastStepIndex) {
          nextWizardStep();
          return;
        }

        if (bookingLocked) {
          statusEl.textContent = t("booking_unavailable");
          return;
        }

        if (!validateDateNotPast()) {
          preferredDate.reportValidity();
          return;
        }
        if (!validateCityForDate({ showMessage: true })) {
          citySelect.reportValidity();
          return;
        }
        if (!validateSelectedTimeNotPast()) {
          preferredTime.reportValidity();
          return;
        }

        if (!form.reportValidity()) {
          return;
        }

        const flaggedField = monitoredFields.find((field) => containsProfanity(field.value || ""));
        if (flaggedField) {
          showBadVibe();
          flaggedField.focus();
          return;
        }

        const emailValue = emailInput ? emailInput.value.trim() : "";
        const phoneValue = form.querySelector("#phone")?.value?.trim() || "";
        if (await checkBlacklist(emailValue, phoneValue)) {
          setBookingLock(t("booking_unavailable"));
          return;
        }

        const formData = new FormData(form);
        const selected = durationSelect.selectedOptions[0];
        const activeSchedule = updateSelectedTourContext();
        const payload = Object.fromEntries(formData.entries());
        payload.duration_label = selected?.dataset.label || "";
        payload.duration_hours = selected?.dataset.hours || "0";
        payload.duration_rate_key = selected?.dataset.rateKey || "";
        payload.currency = currencySelect.value || "";
        payload.deposit_percent = String(DEPOSIT_PERCENT);
        payload.deposit_confirm = formData.get("deposit_confirm") ? "1" : "";
        payload.tour_timezone = selectedTourTimezone || tourTz;
        payload.tour_city = activeSchedule?.city || getCityForDate(preferredDate.value) || tourCity;
        payload.buffer_minutes = String(Number.isFinite(selectedBufferMinutes) ? selectedBufferMinutes : bufferMinutes);
        payload.preferred_time = preferredTime.value || "";

        const parseServerJson = (rawText) => {
          if (!rawText) return null;
          try {
            return JSON.parse(rawText);
          } catch (_error) {
            const start = rawText.indexOf("{");
            const end = rawText.lastIndexOf("}");
            if (start !== -1 && end > start) {
              try {
                return JSON.parse(rawText.slice(start, end + 1));
              } catch (_innerError) {
                return null;
              }
            }
            return null;
          }
        };

        try {
          const response = await fetch("api/request.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify(payload),
          });
          const raw = await response.text();
          const result = parseServerJson(raw) || {};
          if (!response.ok) {
            const fieldError = result?.fields ? Object.values(result.fields)[0] : "";
            throw new Error(result.error || fieldError || `Server error (${response.status}).`);
          }
          const successText = t("success_request_sent", { id: result.id });
          statusEl.textContent = successText;
          const fallbackPayLink = result.id ? `/booking/pay/index.php?id=${encodeURIComponent(result.id)}` : "";
          const paymentLink =
            result.payment_page || (result.payment_link_is_url ? result.payment_link : "") || fallbackPayLink;
          const emailValue = emailInput ? emailInput.value.trim() : "";
          showSuccessPopup(successText, paymentLink, emailValue);
          form.reset();
          currencySelect.value = detectDefaultCurrency();
          await updateDurationOptions();
          toggleOutcall();
          setBookingDateFloor();
          validateCityForDate({ showMessage: true });
          renderDayCalendar();
          updatePaymentSummary();
          renderWizardStep(0);
        } catch (error) {
          statusEl.textContent = `${t("send_error_prefix")} ${error.message}`;
        }
      });

      const setLabelText = (fieldId, text) => {
        const label = document.querySelector(`label[for="${fieldId}"]`);
        if (label) label.textContent = text;
      };
      const setRadioLabelText = (name, value, text) => {
        const input = document.querySelector(`input[name="${name}"][value="${value}"]`);
        const span = input?.closest("label")?.querySelector("span");
        if (span) span.textContent = text;
      };
      const setOptionText = (selectId, value, text) => {
        const option = document.querySelector(`#${selectId} option[value="${value}"]`);
        if (option) option.textContent = text;
      };
      const setDepositRowPrefix = (selector, labelText) => {
        const row = document.querySelector(selector);
        if (!row) return;
        const firstNode = Array.from(row.childNodes).find((node) => node.nodeType === Node.TEXT_NODE);
        if (firstNode) {
          firstNode.nodeValue = `${labelText} `;
        }
      };
      const applyLanguage = async (lang, persist = true) => {
        currentLanguage = SUPPORTED_LANGUAGES.includes(lang) ? lang : "en";
        document.documentElement.setAttribute("lang", currentLanguage);
        if (persist) {
          try {
            window.localStorage.setItem(LANGUAGE_KEY, currentLanguage);
          } catch (_error) {}
        }

        document.title = t("booking_title");
        const backInsideLink = document.getElementById("backInsideLink");
        if (backInsideLink) backInsideLink.textContent = t("back_to_inside");
        const bookingTitle = document.getElementById("bookingTitle");
        if (bookingTitle) bookingTitle.textContent = t("booking_title");
        const bookingSubtitle = document.getElementById("bookingSubtitle");
        if (bookingSubtitle) bookingSubtitle.textContent = t("booking_subtitle");
        const panelRequestTitle = document.getElementById("panelRequestTitle");
        if (panelRequestTitle) panelRequestTitle.textContent = t("request_title");
        const panelAvailabilityTitle = document.getElementById("panelAvailabilityTitle");
        if (panelAvailabilityTitle) panelAvailabilityTitle.textContent = t("availability_title");
        const panelPaymentTitle = document.getElementById("panelPaymentTitle");
        if (panelPaymentTitle) panelPaymentTitle.textContent = t("payment_title");
        if (wizardTitle) wizardTitle.textContent = t("wizard_step_label");

        setLabelText("name", t("label_name"));
        setLabelText("phone", t("label_phone"));
        setLabelText("email", t("label_email"));
        setLabelText("notes", t("label_notes"));
        setLabelText("preferred_date", t("label_date"));
        setLabelText("city", t("label_city"));
        setLabelText("currency", t("label_currency"));
        setLabelText("duration", t("label_duration"));
        setLabelText("experience", t("label_service"));
        setLabelText("outcall_address", t("outcall_address"));
        setLabelText("preferred_time", t("selected_time"));
        setLabelText("payment_method", t("label_payment_method"));

        if (phoneInput) phoneInput.placeholder = t("phone_placeholder");
        const followupPhoneTitle = document.getElementById("followupPhoneTitle");
        if (followupPhoneTitle) followupPhoneTitle.textContent = t("followup_phone_title");
        const followupEmailTitle = document.getElementById("followupEmailTitle");
        if (followupEmailTitle) followupEmailTitle.textContent = t("followup_email_title");
        const followupPhoneCitiesLabel = document.getElementById("followupPhoneCitiesLabel");
        if (followupPhoneCitiesLabel) followupPhoneCitiesLabel.textContent = t("other_cities");
        const followupEmailCitiesLabel = document.getElementById("followupEmailCitiesLabel");
        if (followupEmailCitiesLabel) followupEmailCitiesLabel.textContent = t("other_cities");
        const followupPhoneCities = document.getElementById("followup_phone_other_cities");
        if (followupPhoneCities) followupPhoneCities.placeholder = t("followup_phone_placeholder");
        const followupEmailCities = document.getElementById("followup_email_other_cities");
        if (followupEmailCities) followupEmailCities.placeholder = t("followup_email_placeholder");
        if (notesInput) notesInput.placeholder = t("notes_placeholder");
        const notesHint = document.querySelector("#notesField .hint");
        if (notesHint) notesHint.textContent = t("notes_hint");

        setRadioLabelText("contact_followup_phone", "yes", t("yes"));
        setRadioLabelText("contact_followup_phone", "no", t("no"));
        setRadioLabelText("contact_followup_email", "yes", t("yes"));
        setRadioLabelText("contact_followup_email", "no", t("no"));
        setRadioLabelText("booking_type", "incall", t("incall"));
        setRadioLabelText("booking_type", "outcall", t("outcall"));

        const bookingLocationLabel = document.querySelector("#bookingTypeField > label:not(.radio-card)");
        if (bookingLocationLabel) bookingLocationLabel.textContent = t("label_location");
        const outcallHint = document.querySelector("#bookingTypeField .hint");
        if (outcallHint) outcallHint.textContent = t("outcall_hint");

        const touringDayNote = document.getElementById("touringDayNote");
        if (touringDayNote && touringDayNote.childNodes.length) {
          touringDayNote.childNodes[0].nodeValue = `${t("touring_day_prefix")} `;
        }
        if (touringDayCityEl && !preferredDate?.value) {
          touringDayCityEl.textContent = t("select_date");
        }
        if (cityMismatchWarning && cityMismatchWarning.classList.contains("hidden")) {
          cityMismatchWarning.textContent = t("city_no_tour");
        }

        const timezoneHintLine = document.getElementById("timezoneHintLine");
        if (timezoneHintLine && timezoneHintLine.childNodes.length) {
          timezoneHintLine.childNodes[0].nodeValue = `${t("timezone_hint_prefix")} `;
        }
        if (availabilityNote) availabilityNote.textContent = t("availability_note_default");
        if (durationHint) durationHint.textContent = t("duration_hint");
        if (whatsappDiscuss) {
          whatsappDiscuss.textContent = t("discuss_whatsapp");
          whatsappDiscuss.setAttribute("aria-label", t("discuss_whatsapp"));
        }

        setOptionText("city", "", t("choose_option"));
        setOptionText("currency", "", t("choose_option"));
        setOptionText("experience", "", t("choose_option"));
        setOptionText("payment_method", "", t("choose_option"));
        setOptionText("payment_method", "interac", t("etransfer_canada"));

        setDepositRowPrefix("#depositBox .deposit-total:nth-of-type(1)", t("base_rate"));
        setDepositRowPrefix("#pseAddonRow", t("pse_addon"));
        setDepositRowPrefix("#depositBox .deposit-total:nth-of-type(3)", t("total_rate"));
        setDepositRowPrefix("#depositBox .deposit-total:nth-of-type(4)", t("deposit_rate"));
        const depositConsentText = document.getElementById("depositConsentText");
        if (depositConsentText) depositConsentText.textContent = t("deposit_consent");
        if (paymentCryptoHint) paymentCryptoHint.textContent = t("crypto_hint");
        if (submitBtn) submitBtn.textContent = t("request_booking");
        if (wizardBack) wizardBack.textContent = t("back");
        if (wizardNext) wizardNext.textContent = t("next");
        if (successTitle) successTitle.textContent = t("success_title");
        if (successLink) successLink.textContent = t("pay_now");
        if (successClose) successClose.textContent = t("got_it");
        if (alertPopupTitle) alertPopupTitle.textContent = t("alert_title");
        if (alertPopupClose) alertPopupClose.textContent = t("alert_ok");
        if (depositRateNote) depositRateNote.textContent = t("deposit_note");

        languageButtons.forEach((button) => {
          button.setAttribute("aria-pressed", button.dataset.languageChoice === currentLanguage ? "true" : "false");
        });

        await updateDurationOptions();
        await updatePaymentSummary();
        renderWizardStep(wizardStepIndex);
        renderDayCalendar();
      };

      languageButtons.forEach((button) => {
        button.addEventListener("click", () => {
          applyLanguage(button.dataset.languageChoice, true);
        });
      });

      currencySelect.value = detectDefaultCurrency();
      setBookingDateFloor();
      updateDurationOptions();
      toggleOutcall();
      validateCityForDate({ showMessage: true });
      renderDayCalendar();
      renderWizardStep(0);
      loadTourSchedule();
      loadAvailability();
      checkBlacklistOnLoad();
      const initialLanguage = getStoredLanguage() || detectBrowserLanguage();
      applyLanguage(initialLanguage, true);
    