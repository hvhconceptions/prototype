
      const ADMIN_KEY = "";
      const TIMEZONES = [
        "America/Toronto",
        "America/New_York",
        "America/Chicago",
        "America/Denver",
        "America/Los_Angeles",
        "America/Vancouver",
        "America/Mexico_City",
        "America/Sao_Paulo",
        "Europe/London",
        "Europe/Paris",
        "Europe/Berlin",
        "Europe/Rome",
        "Europe/Madrid",
        "Europe/Amsterdam",
        "Europe/Zurich",
        "Europe/Stockholm",
        "Europe/Athens",
        "Europe/Moscow",
        "Africa/Cairo",
        "Africa/Johannesburg",
        "Asia/Dubai",
        "Asia/Jerusalem",
        "Asia/Kolkata",
        "Asia/Bangkok",
        "Asia/Singapore",
        "Asia/Shanghai",
        "Asia/Hong_Kong",
        "Asia/Tokyo",
        "Asia/Seoul",
        "Australia/Sydney",
        "Australia/Melbourne",
        "Pacific/Auckland",
      ];

      const tourCityInput = document.getElementById("tourCity");
      const tourTzSelect = document.getElementById("tourTimezone");
      const bufferInput = document.getElementById("bufferMinutes");
      const availabilityStatus = document.getElementById("availabilityStatus");
      const saveAvailabilityBtn = document.getElementById("saveAvailability");
      const calendarStart = document.getElementById("calendarStart");
      const calendarDay = document.getElementById("calendarDay");
      const calendarMonth = document.getElementById("calendarMonth");
      const calendarToday = document.getElementById("calendarToday");
      const calendarGrid = document.getElementById("calendarGrid");
      const calendarViewButtons = document.querySelectorAll("[data-calendar-view]");
      const calendarFields = document.querySelectorAll("[data-calendar-field]");
      const blockedDate = document.getElementById("blockedDate");
      const blockedStart = document.getElementById("blockedStart");
      const blockedEnd = document.getElementById("blockedEnd");
      const blockedReason = document.getElementById("blockedReason");
      const blockedFullRangeStart = document.getElementById("blockedFullRangeStart");
      const blockedFullRangeEnd = document.getElementById("blockedFullRangeEnd");
      const blockedStatus = document.getElementById("blockedStatus");
      const blockedList = document.getElementById("blockedList");
      const addBlockedBtn = document.getElementById("addBlocked");
      const blockFullDayBtn = document.getElementById("blockFullDay");
      const blockFullRangeBtn = document.getElementById("blockFullRange");
      const recurringDays = document.getElementById("recurringDays");
      const recurringAllDay = document.getElementById("recurringAllDay");
      const recurringStart = document.getElementById("recurringStart");
      const recurringEnd = document.getElementById("recurringEnd");
      const recurringReason = document.getElementById("recurringReason");
      const addRecurringBtn = document.getElementById("addRecurring");
      const recurringStatus = document.getElementById("recurringStatus");
      const recurringList = document.getElementById("recurringList");
      const toggleBlockedListBtn = document.getElementById("toggleBlockedList");
      const refreshBtn = document.getElementById("refreshRequests");
      const statusFilter = document.getElementById("statusFilter");
      const requestsList = document.getElementById("requestsList");
      const requestsStatus = document.getElementById("requestsStatus");
      const tourScheduleList = document.getElementById("tourScheduleList");
      const tourScheduleStatus = document.getElementById("tourScheduleStatus");
      const addTourRowBtn = document.getElementById("addTourRow");
      const saveTourScheduleBtn = document.getElementById("saveTourSchedule");
      const cityScheduleWizard = document.getElementById("cityScheduleWizard");
      const cityScheduleStatus = document.getElementById("cityScheduleStatus");
      const clearCityTemplatesBtn = document.getElementById("clearCityTemplates");
      const galleryList = document.getElementById("galleryList");
      const galleryStatus = document.getElementById("galleryStatus");
      const addGalleryRowBtn = document.getElementById("addGalleryRow");
      const saveGalleryBtn = document.getElementById("saveGallery");
      const languageButtons = document.querySelectorAll(".age-language [data-language-choice]");
      const LANGUAGE_KEY = "hvh_inside_language";
      const SUPPORTED_LANGUAGES = ["en", "fr"];
      let currentLanguage = "en";
      const I18N = {
        en: {
          admin_title: "BombaCLOUD!",
          admin_subtitle: "Simple city-by-city setup. Fill each city card, save, then manage requests.",
          tour_schedule_title: "Tour schedule",
          tour_schedule_hint: "Dates are inclusive. Use YYYY-MM-DD and avoid overlaps.",
          add_stop: "Add stop",
          save_tour_schedule: "Save tour schedule",
          city_wizard_title: "City schedule wizard",
          city_wizard_hint:
            "One card per touring city/date range. Fill question-by-question to auto-build each city schedule with timezone handling.",
          city_wizard_timezone_hint: "Timezone is shown automatically for each city card.",
          save_city_schedule: "Save city schedule",
          clear_template_blocks: "Clear template blocks",
          love_logo: "Love logo",
          eye_candy_title: "Eye candy",
          eye_candy_hint: "Use full paths like /photos/heidi15.jpg and short alt text.",
          add_eye_candy: "Add eye candy",
          save_eye_candy: "Save eye candy",
          requests_title: "Requests",
          start_date: "Start date",
          end_date: "End date",
          city_field: "City",
          city_name_placeholder: "City name",
          photo_path: "Photo path",
          alt_text: "Alt text",
          short_description: "Short description",
          preview: "Preview",
          refresh: "Refresh",
          all: "All",
          pending: "Pending",
          accepted: "Accepted",
          paid: "Paid",
          blacklisted: "Blacklisted",
          declined: "Declined",
          cancelled: "Cancelled",
          costumer_directory: "Costumer directory",
          remove: "Remove",
          add_tour_first: "Add tour stops first, then city schedule cards appear here.",
          add_photo_min: "Add at least one eye candy photo.",
          add_entry_min: "Add at least one valid entry.",
          admin_key_required: "Admin key required.",
          city_schedule_saved: "City schedule saved.",
          city_schedules_saved: "City schedules saved.",
          failed_save_city_schedule: "Failed to save city schedule.",
          unsaved_changes: "Unsaved changes. Click Save city schedule.",
          templates_changed: "City templates changed. Click Save city schedule.",
          saving_city_schedule: "Saving city schedule...",
          city_updated_applied: "{city} updated and applied.",
          template_rules_cleared: "Template rules cleared.",
          template_rules_clearing: "Template rules cleared. Saving now...",
          failed_load_tour_schedule: "Failed to load tour schedule.",
          tour_schedule_saved: "Tour schedule saved.",
          tour_dates_changed: "Tour dates changed. Click Save city schedule to update city templates.",
          failed_save_tour_schedule: "Failed to save tour schedule.",
          failed_load_eye_candy: "Failed to load eye candy.",
          eye_candy_saved: "Eye candy saved.",
          failed_save_eye_candy: "Failed to save eye candy.",
          status_updated: "Status updated.",
          failed_update_status: "Failed to update status.",
          download_ready: "Download ready.",
          failed_download: "Failed to download.",
          no_requests_found: "No requests found.",
          failed_load_requests: "Failed to load requests.",
          unable_load_city_schedule: "Unable to load city schedule.",
          pick_valid_ready_time: "Pick a valid ready time.",
          buffer_invalid: "Buffer must be 0 or higher.",
          pick_sleep_times: "Pick sleep start and end time.",
          pick_sleep_days: "Select at least one day for sleep pattern.",
          pick_break_times: "Pick break start and end time.",
          pick_break_days: "Select at least one day for breaks.",
          pick_leave_day_end: "Pick leave-day end time.",
          tour_stop: "Tour stop",
          date_to: "to",
          q_ready_time: "What time will you be ready to work at?",
          q_buffer: "Do you need a buffer in between clients?",
          q_sleep: "Do you have a sleeping pattern?",
          q_breaks: "Do you take any breaks during your stay?",
          q_leave_time: "Until what time will you be taking costumers on your leaving day?",
          which_days: "Which days?",
          yes: "Yes",
          no: "No",
          back: "Back",
          next: "Next",
          done: "Done",
          timezone: "Timezone",
          not_set: "Not set",
          step: "Step {current} of {total}",
          follow_up: "Follow-up",
          no_city: "no city",
          follow_up_no: "no",
          email_label: "Email",
          phone_label: "Phone",
          city_label: "City",
          type_label: "Type",
          outcall_address: "Outcall address",
          experience: "Experience",
          duration: "Duration",
          preferred: "Preferred",
          client_tz: "Client TZ",
          tour_tz: "Tour TZ",
          deposit: "Deposit",
          payment_status: "Payment status",
          payment_method: "Payment method",
          notes: "Notes",
          decline_reason: "Decline reason",
          reason: "Reason",
          blacklist_reason: "Blacklist reason",
          payment_details: "Payment details",
          created: "Created",
          updated: "Updated",
          email_sent: "Email sent",
          unknown: "Unknown",
          action_accept: "Accept",
          action_blacklist: "Blacklist",
          action_mark_paid: "Mark paid",
          action_decline: "Decline",
          action_cancel: "Cancel",
          action_google_calendar: "Add to Google Calendar",
          action_samsung_calendar: "Samsung Calendar (.ics)",
        },
        fr: {
          admin_title: "BombaCLOUD!",
          admin_subtitle: "Configuration simple ville par ville. Remplissez chaque carte, sauvegardez, puis gerez les demandes.",
          tour_schedule_title: "Calendrier de tournee",
          tour_schedule_hint: "Les dates sont inclusives. Utilisez YYYY-MM-DD et evitez les chevauchements.",
          add_stop: "Ajouter une ville",
          save_tour_schedule: "Sauvegarder la tournee",
          city_wizard_title: "Assistant planning par ville",
          city_wizard_hint:
            "Une carte par ville/periode de tournee. Remplissez question par question pour generer le planning automatiquement.",
          city_wizard_timezone_hint: "Le fuseau horaire est affiche automatiquement pour chaque carte ville.",
          save_city_schedule: "Sauvegarder le planning ville",
          clear_template_blocks: "Effacer les blocs modele",
          love_logo: "Logo love",
          eye_candy_title: "Eye candy",
          eye_candy_hint: "Utilisez des chemins complets comme /photos/heidi15.jpg et un court texte alt.",
          add_eye_candy: "Ajouter eye candy",
          save_eye_candy: "Sauvegarder eye candy",
          requests_title: "Demandes",
          start_date: "Date de debut",
          end_date: "Date de fin",
          city_field: "Ville",
          city_name_placeholder: "Nom de ville",
          photo_path: "Chemin photo",
          alt_text: "Texte alt",
          short_description: "Description courte",
          preview: "Apercu",
          refresh: "Actualiser",
          all: "Tous",
          pending: "En attente",
          accepted: "Acceptee",
          paid: "Payee",
          blacklisted: "Liste noire",
          declined: "Refusee",
          cancelled: "Annulee",
          costumer_directory: "Repertoire clients",
          remove: "Retirer",
          add_tour_first: "Ajoutez d'abord des etapes de tournee, puis les cartes ville apparaissent ici.",
          add_photo_min: "Ajoutez au moins une photo eye candy.",
          add_entry_min: "Ajoutez au moins une entree valide.",
          admin_key_required: "Cle admin requise.",
          city_schedule_saved: "Planning ville sauvegarde.",
          city_schedules_saved: "Plannings ville sauvegardes.",
          failed_save_city_schedule: "Echec sauvegarde planning ville.",
          unsaved_changes: "Changements non sauvegardes. Cliquez sur Sauvegarder le planning ville.",
          templates_changed: "Modeles ville modifies. Cliquez sur Sauvegarder le planning ville.",
          saving_city_schedule: "Sauvegarde du planning ville...",
          city_updated_applied: "{city} mis a jour et applique.",
          template_rules_cleared: "Regles modele effacees.",
          template_rules_clearing: "Regles modele effacees. Sauvegarde en cours...",
          failed_load_tour_schedule: "Echec chargement tournee.",
          tour_schedule_saved: "Tournee sauvegardee.",
          tour_dates_changed: "Dates de tournee modifiees. Sauvegardez le planning ville pour mettre a jour les modeles.",
          failed_save_tour_schedule: "Echec sauvegarde tournee.",
          failed_load_eye_candy: "Echec chargement eye candy.",
          eye_candy_saved: "Eye candy sauvegarde.",
          failed_save_eye_candy: "Echec sauvegarde eye candy.",
          status_updated: "Statut mis a jour.",
          failed_update_status: "Echec mise a jour statut.",
          download_ready: "Telechargement pret.",
          failed_download: "Echec telechargement.",
          no_requests_found: "Aucune demande trouvee.",
          failed_load_requests: "Echec chargement demandes.",
          unable_load_city_schedule: "Impossible de charger le planning ville.",
          pick_valid_ready_time: "Choisissez une heure de debut valide.",
          buffer_invalid: "Le buffer doit etre 0 ou plus.",
          pick_sleep_times: "Choisissez heure debut/fin sommeil.",
          pick_sleep_days: "Choisissez au moins un jour pour le sommeil.",
          pick_break_times: "Choisissez heure debut/fin pause.",
          pick_break_days: "Choisissez au moins un jour pour les pauses.",
          pick_leave_day_end: "Choisissez l'heure de fin du jour de depart.",
          tour_stop: "Etape tournee",
          date_to: "a",
          q_ready_time: "A quelle heure serez-vous prete a travailler?",
          q_buffer: "Avez-vous besoin d'un buffer entre les clients?",
          q_sleep: "Avez-vous un rythme de sommeil?",
          q_breaks: "Prenez-vous des pauses pendant votre sejour?",
          q_leave_time: "Jusqu'a quelle heure prenez-vous des clients le jour du depart?",
          which_days: "Quels jours?",
          yes: "Oui",
          no: "Non",
          back: "Retour",
          next: "Suivant",
          done: "Terminer",
          timezone: "Fuseau",
          not_set: "Non defini",
          step: "Etape {current} sur {total}",
          follow_up: "Suivi",
          no_city: "aucune ville",
          follow_up_no: "non",
          email_label: "Email",
          phone_label: "Telephone",
          city_label: "Ville",
          type_label: "Type",
          outcall_address: "Adresse outcall",
          experience: "Experience",
          duration: "Duree",
          preferred: "Souhaite",
          client_tz: "Fuseau client",
          tour_tz: "Fuseau tournee",
          deposit: "Acompte",
          payment_status: "Statut paiement",
          payment_method: "Methode paiement",
          notes: "Notes",
          decline_reason: "Raison du refus",
          reason: "Raison",
          blacklist_reason: "Raison liste noire",
          payment_details: "Details paiement",
          created: "Cree",
          updated: "Mis a jour",
          email_sent: "Email envoye",
          unknown: "Inconnu",
          action_accept: "Accepter",
          action_blacklist: "Liste noire",
          action_mark_paid: "Marquer paye",
          action_decline: "Refuser",
          action_cancel: "Annuler",
          action_google_calendar: "Ajouter a Google Calendar",
          action_samsung_calendar: "Samsung Calendar (.ics)",
        },
      };
      const formatTemplate = (template, vars = {}) =>
        String(template || "").replace(/\{(\w+)\}/g, (_m, key) => (vars[key] ?? `{${key}}`));
      const t = (key, vars = {}) => {
        const pack = I18N[currentLanguage] || I18N.en;
        const fallback = I18N.en[key] || key;
        return formatTemplate(pack[key] || fallback, vars);
      };
      const statusLabel = (status) => t(String(status || "").toLowerCase());
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
      const setTextById = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
      };
      const applyLanguage = async (lang, persist = true) => {
        currentLanguage = SUPPORTED_LANGUAGES.includes(lang) ? lang : "en";
        document.documentElement.setAttribute("lang", currentLanguage);
        if (persist) {
          try {
            window.localStorage.setItem(LANGUAGE_KEY, currentLanguage);
          } catch (_error) {}
        }

        document.title = t("admin_title");
        setTextById("adminMainTitle", t("admin_title"));
        setTextById("adminSubtitle", t("admin_subtitle"));
        setTextById("tourScheduleTitle", t("tour_schedule_title"));
        setTextById("tourScheduleHint", t("tour_schedule_hint"));
        setTextById("addTourRow", t("add_stop"));
        setTextById("saveTourSchedule", t("save_tour_schedule"));
        setTextById("cityWizardTitle", t("city_wizard_title"));
        setTextById("cityWizardHint", t("city_wizard_hint"));
        setTextById("cityWizardTimezoneHint", t("city_wizard_timezone_hint"));
        setTextById("saveAvailability", t("save_city_schedule"));
        setTextById("clearCityTemplates", t("clear_template_blocks"));
        setTextById("cityLoveLogo", t("love_logo"));
        setTextById("gallerySectionTitle", t("eye_candy_title"));
        setTextById("gallerySectionHint", t("eye_candy_hint"));
        setTextById("addGalleryRow", t("add_eye_candy"));
        setTextById("saveGallery", t("save_eye_candy"));
        setTextById("requestsSectionTitle", t("requests_title"));
        setTextById("refreshRequests", t("refresh"));
        const customersLink = document.querySelector('a[href="customers.php"]');
        if (customersLink) customersLink.textContent = t("costumer_directory");

        const statusLabels = {
          all: t("all"),
          pending: t("pending"),
          accepted: t("accepted"),
          paid: t("paid"),
          blacklisted: t("blacklisted"),
          declined: t("declined"),
          cancelled: t("cancelled"),
        };
        Object.entries(statusLabels).forEach(([value, label]) => {
          const option = document.querySelector(`#statusFilter option[value="${value}"]`);
          if (option) option.textContent = label;
        });

        languageButtons.forEach((button) => {
          button.setAttribute("aria-pressed", button.dataset.languageChoice === currentLanguage ? "true" : "false");
        });

        renderTourSchedule(touringStops);
        renderGallery(readGalleryFromUI().length ? readGalleryFromUI() : []);
        renderCityScheduleWizard();
        await loadRequests();
      };

      languageButtons.forEach((button) => {
        button.addEventListener("click", () => {
          applyLanguage(button.dataset.languageChoice, true);
        });
      });

      if (statusFilter) {
        statusFilter.value = "pending";
      }

      const getKey = () => ADMIN_KEY;

      const headersWithKey = () => {
        const key = getKey();
        if (!key) return {};
        return { "X-Admin-Key": key };
      };

      const populateTimezones = () => {
        if (!tourTzSelect) return;
        tourTzSelect.innerHTML = "";
        TIMEZONES.forEach((zone) => {
          const option = document.createElement("option");
          option.value = zone;
          option.textContent = zone.replace("_", " ");
          tourTzSelect.appendChild(option);
        });
      };

      let blockedSlots = [];
      let recurringBlocks = [];
      let touringStops = [];
      let citySchedules = [];
      const SLOT_MINUTES = 30;
      const SLOT_TIMES = Array.from({ length: 48 }, (_, index) => {
        const total = index * SLOT_MINUTES;
        const hour = String(Math.floor(total / 60)).padStart(2, "0");
        const minute = String(total % 60).padStart(2, "0");
        return `${hour}:${minute}`;
      });

      const timeToMinutes = (timeValue) => {
        const [hour, minute] = String(timeValue || "").split(":").map((value) => Number(value));
        if (Number.isNaN(hour) || Number.isNaN(minute)) return null;
        return hour * 60 + minute;
      };

      const minutesToTime = (minutes) => {
        const hour = String(Math.floor(minutes / 60)).padStart(2, "0");
        const minute = String(minutes % 60).padStart(2, "0");
        return `${hour}:${minute}`;
      };

      const toDateKey = (date) => {
        const year = String(date.getUTCFullYear()).padStart(4, "0");
        const month = String(date.getUTCMonth() + 1).padStart(2, "0");
        const day = String(date.getUTCDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
      };

      const parseDateKey = (value) => {
        const parts = String(value || "").split("-").map((piece) => Number(piece));
        if (parts.length !== 3) return null;
        const [year, month, day] = parts;
        if (!year || !month || !day) return null;
        return new Date(Date.UTC(year, month - 1, day));
      };

      const CITY_TIMEZONE_MAP = {
        montreal: "America/Toronto",
        toronto: "America/Toronto",
        vancouver: "America/Vancouver",
        "london (uk)": "Europe/London",
        london: "Europe/London",
        berlin: "Europe/Berlin",
        paris: "Europe/Paris",
      };

      const normalizeCityName = (value) =>
        String(value || "")
          .trim()
          .toLowerCase()
          .replace(/\s+/g, " ");

      const makeScheduleId = (entry) =>
        `${String(entry?.start || "").trim()}|${String(entry?.end || "").trim()}|${normalizeCityName(entry?.city || "")}`;

      const getDefaultTimezoneForCity = (city) => {
        const mapped = CITY_TIMEZONE_MAP[normalizeCityName(city)];
        if (mapped) {
          return mapped;
        }
        return TIMEZONES[0];
      };

      const isValidTime = (value) => /^\d{2}:\d{2}$/.test(String(value || ""));

      const normalizeCitySchedule = (entry = {}) => {
        const city = String(entry.city || "").trim();
        const start = String(entry.start || "").trim();
        const end = String(entry.end || "").trim();
        const timezone = getDefaultTimezoneForCity(city);
        const dateKeys = (() => {
          const startDate = parseDateKey(start);
          const endDate = parseDateKey(end);
          if (!startDate || !endDate || startDate > endDate) {
            return [];
          }
          const list = [];
          const cursor = new Date(startDate.getTime());
          while (cursor <= endDate) {
            list.push(toDateKey(cursor));
            cursor.setUTCDate(cursor.getUTCDate() + 1);
          }
          return list;
        })();
        const validDateSet = new Set(dateKeys);
        const hasSleepDaysField = Array.isArray(entry.sleep_days);
        const hasBreakDaysField = Array.isArray(entry.break_days);
        const sleepDaysRaw = hasSleepDaysField ? entry.sleep_days : [];
        const breakDaysRaw = hasBreakDaysField ? entry.break_days : [];
        const bufferMinutes = Math.max(0, Math.min(240, Number(entry.buffer_minutes ?? 0) || 0));
        const readyStart = isValidTime(entry.ready_start) ? String(entry.ready_start) : "11:00";
        const leaveDayEnd = isValidTime(entry.leave_day_end) ? String(entry.leave_day_end) : "18:00";
        const hasSleep = !!entry.has_sleep;
        const sleepDays = hasSleep
          ? Array.from(
              new Set(
                (Array.isArray(sleepDaysRaw) ? sleepDaysRaw : [])
                  .map((value) => String(value || "").trim())
                  .filter((value) => validDateSet.has(value))
              )
            )
          : [];
        const sleepStart = isValidTime(entry.sleep_start) ? String(entry.sleep_start) : "02:00";
        const sleepEnd = isValidTime(entry.sleep_end) ? String(entry.sleep_end) : "10:00";
        const hasBreak = !!entry.has_break;
        const breakDays = hasBreak
          ? Array.from(
              new Set(
                (Array.isArray(breakDaysRaw) ? breakDaysRaw : [])
                  .map((value) => String(value || "").trim())
                  .filter((value) => validDateSet.has(value))
              )
            )
          : [];
        const breakStart = isValidTime(entry.break_start) ? String(entry.break_start) : "16:00";
        const breakEnd = isValidTime(entry.break_end) ? String(entry.break_end) : "17:00";
        const step = Math.max(0, Math.min(4, Number(entry._step) || 0));
        const defaultSleepDays = hasSleep && !hasSleepDaysField ? dateKeys : sleepDays;
        const defaultBreakDays = hasBreak && !hasBreakDaysField ? dateKeys : breakDays;
        return {
          id: makeScheduleId({ city, start, end }),
          city,
          start,
          end,
          timezone,
          ready_start: readyStart,
          buffer_minutes: bufferMinutes,
          has_sleep: hasSleep,
          sleep_days: defaultSleepDays,
          sleep_start: sleepStart,
          sleep_end: sleepEnd,
          has_break: hasBreak,
          break_days: defaultBreakDays,
          break_start: breakStart,
          break_end: breakEnd,
          leave_day_end: leaveDayEnd,
          _step: step,
        };
      };

      const formatDateLabel = (dateKey) => {
        const date = parseDateKey(dateKey);
        if (!date) {
          return dateKey || "";
        }
        return new Intl.DateTimeFormat("en-US", {
          timeZone: "UTC",
          month: "short",
          day: "numeric",
          year: "numeric",
        }).format(date);
      };

      const getDateRangeKeys = (startKey, endKey) => {
        const startDate = parseDateKey(startKey);
        const endDate = parseDateKey(endKey);
        if (!startDate || !endDate || startDate > endDate) {
          return [];
        }
        const dates = [];
        const cursor = new Date(startDate.getTime());
        while (cursor <= endDate) {
          dates.push(toDateKey(cursor));
          cursor.setUTCDate(cursor.getUTCDate() + 1);
        }
        return dates;
      };

      const pushTemplateRange = (target, schedule, dateKey, startTime, endTime, reason) => {
        const startMinutes = timeToMinutes(startTime);
        const endMinutes = timeToMinutes(endTime);
        if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
          return;
        }
        for (let minutes = startMinutes; minutes < endMinutes; minutes += SLOT_MINUTES) {
          const next = Math.min(minutes + SLOT_MINUTES, endMinutes);
          target.push({
            date: dateKey,
            start: minutesToTime(minutes),
            end: next >= 1440 ? "23:59" : minutesToTime(next),
            reason,
            kind: "template",
            template_id: schedule.id,
            city: schedule.city,
          });
        }
      };

      const buildTemplateBlocksForSchedule = (schedule) => {
        const blocks = [];
        const dates = getDateRangeKeys(schedule.start, schedule.end);
        if (!dates.length) {
          return blocks;
        }
        const sleepDaySet = new Set(Array.isArray(schedule.sleep_days) ? schedule.sleep_days : []);
        const breakDaySet = new Set(Array.isArray(schedule.break_days) ? schedule.break_days : []);
        const startTime = isValidTime(schedule.ready_start) ? schedule.ready_start : "11:00";
        const leaveEnd = isValidTime(schedule.leave_day_end) ? schedule.leave_day_end : "18:00";
        dates.forEach((dateKey, index) => {
          pushTemplateRange(blocks, schedule, dateKey, "00:00", startTime, "Before ready time");
          if (index === dates.length - 1) {
            pushTemplateRange(blocks, schedule, dateKey, leaveEnd, "23:59", "After leave-day end");
          }
          if (
            schedule.has_sleep &&
            sleepDaySet.has(dateKey) &&
            isValidTime(schedule.sleep_start) &&
            isValidTime(schedule.sleep_end)
          ) {
            const sleepStart = timeToMinutes(schedule.sleep_start);
            const sleepEnd = timeToMinutes(schedule.sleep_end);
            if (sleepStart !== null && sleepEnd !== null) {
              if (sleepStart < sleepEnd) {
                pushTemplateRange(blocks, schedule, dateKey, schedule.sleep_start, schedule.sleep_end, "Sleep");
              } else if (sleepStart > sleepEnd) {
                pushTemplateRange(blocks, schedule, dateKey, schedule.sleep_start, "23:59", "Sleep");
                const nextDate = dates[index + 1];
                if (nextDate) {
                  pushTemplateRange(blocks, schedule, nextDate, "00:00", schedule.sleep_end, "Sleep");
                }
              }
            }
          }
          if (
            schedule.has_break &&
            breakDaySet.has(dateKey) &&
            isValidTime(schedule.break_start) &&
            isValidTime(schedule.break_end)
          ) {
            const breakStart = timeToMinutes(schedule.break_start);
            const breakEnd = timeToMinutes(schedule.break_end);
            if (breakStart !== null && breakEnd !== null) {
              if (breakStart < breakEnd) {
                pushTemplateRange(blocks, schedule, dateKey, schedule.break_start, schedule.break_end, "Break");
              } else if (breakStart > breakEnd) {
                pushTemplateRange(blocks, schedule, dateKey, schedule.break_start, "23:59", "Break");
                const nextDate = dates[index + 1];
                if (nextDate) {
                  pushTemplateRange(blocks, schedule, nextDate, "00:00", schedule.break_end, "Break");
                }
              }
            }
          }
        });
        return blocks;
      };

      const getTemplateBlocks = () => {
        const generated = [];
        citySchedules.forEach((schedule) => {
          generated.push(...buildTemplateBlocksForSchedule(schedule));
        });
        return generated;
      };

      const applyCityTemplateBlocks = ({ announce = true } = {}) => {
        const baseSlots = blockedSlots.filter((slot) => slot && slot.kind !== "template");
        const templateSlots = getTemplateBlocks();
        blockedSlots = normalizeBlockedSlots([...baseSlots, ...templateSlots]);
        renderBlockedSlots();
        renderCalendarView();
        if (announce && cityScheduleStatus) {
          cityScheduleStatus.textContent = "City templates applied.";
        }
      };

      const blockFullDayForDate = (dateKey, reason) => {
        blockedSlots = blockedSlots.filter(
          (slot) => !slot || slot.date !== dateKey || (slot.kind !== "manual" && slot.kind !== "template")
        );
        const endOfDayMinutes = 24 * 60;
        for (let minutes = 0; minutes < endOfDayMinutes; minutes += SLOT_MINUTES) {
          const next = Math.min(minutes + SLOT_MINUTES, endOfDayMinutes);
          const endTime = next === endOfDayMinutes ? "23:59" : minutesToTime(next);
          blockedSlots.push({
            date: dateKey,
            start: minutesToTime(minutes),
            end: endTime,
            reason,
            kind: "manual",
          });
        }
      };

      const normalizeBlockedSlots = (slots) => {
        const normalized = [];
        (Array.isArray(slots) ? slots : []).forEach((slot) => {
          if (!slot || !slot.date || !slot.start || !slot.end) return;
          const startMinutes = timeToMinutes(slot.start);
          const endMinutes = timeToMinutes(slot.end);
          if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) return;
          const kind = slot.kind || "manual";
          const bookingType = slot.booking_type || "";
          const bookingStatus = slot.booking_status || "";
          const label = slot.label || "";
          const templateId = slot.template_id || "";
          const city = slot.city || "";
          for (let minutes = startMinutes; minutes < endMinutes; minutes += SLOT_MINUTES) {
            normalized.push({
              date: slot.date,
              start: minutesToTime(minutes),
              end: minutesToTime(Math.min(minutes + SLOT_MINUTES, endMinutes)),
              reason: slot.reason || "",
              kind,
              booking_type: bookingType,
              booking_status: bookingStatus,
              label,
              booking_id: slot.booking_id || "",
              template_id: templateId,
              city,
            });
          }
        });
        return normalized;
      };

      const getDateKey = (date) => {
        const parts = new Intl.DateTimeFormat("en-CA", {
          timeZone: tourTzSelect.value,
          year: "numeric",
          month: "2-digit",
          day: "2-digit",
        }).formatToParts(date);
        const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));
        return `${values.year}-${values.month}-${values.day}`;
      };

      const getWeekDates = (startKey) => {
        const [year, month, day] = startKey.split("-").map((value) => Number(value));
        const base = new Date(Date.UTC(year, month - 1, day));
        return Array.from({ length: 7 }, (_, index) => {
          const date = new Date(base.getTime() + index * 86400000);
          const y = String(date.getUTCFullYear()).padStart(4, "0");
          const m = String(date.getUTCMonth() + 1).padStart(2, "0");
          const d = String(date.getUTCDate()).padStart(2, "0");
          return `${y}-${m}-${d}`;
        });
      };

      const formatDayLabel = (dateKey) => {
        const [year, month, day] = dateKey.split("-").map((value) => Number(value));
        const labelDate = new Date(Date.UTC(year, month - 1, day, 12, 0));
        return new Intl.DateTimeFormat("en-US", {
          timeZone: tourTzSelect.value,
          weekday: "short",
          month: "short",
          day: "numeric",
        }).format(labelDate);
      };

      const getWeekdayIndex = (dateKey) => {
        const parts = dateKey.split("-").map((value) => Number(value));
        if (parts.length !== 3) return 0;
        const labelDate = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2], 12, 0));
        const label = new Intl.DateTimeFormat("en-US", {
          timeZone: tourTzSelect.value,
          weekday: "short",
        }).format(labelDate);
        const map = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
        return map[label] ?? 0;
      };

      const isRecurringSlot = (dateKey, timeValue) => {
        if (!Array.isArray(recurringBlocks) || !recurringBlocks.length) return false;
        const targetMinutes = timeToMinutes(timeValue);
        if (targetMinutes === null) return false;
        const weekdayIndex = getWeekdayIndex(dateKey);
        return recurringBlocks.some((block) => {
          if (!block) return false;
          const days = Array.isArray(block.days) ? block.days : [];
          if (!days.includes(weekdayIndex)) return false;
          if (block.all_day) return true;
          const startMinutes = timeToMinutes(block.start);
          const endMinutes = timeToMinutes(block.end);
          if (startMinutes === null || endMinutes === null) return false;
          return targetMinutes >= startMinutes && targetMinutes < endMinutes;
        });
      };

      const getSlotEntry = (dateKey, timeValue) => {
        const targetMinutes = timeToMinutes(timeValue);
        if (targetMinutes === null) return null;
        let entry = null;
        blockedSlots.forEach((slot) => {
          if (!slot || slot.date !== dateKey) return;
          const startMinutes = timeToMinutes(slot.start);
          const endMinutes = timeToMinutes(slot.end);
          if (startMinutes === null || endMinutes === null) return;
          if (targetMinutes >= startMinutes && targetMinutes < endMinutes) {
            if (!entry || (entry.kind !== "booking" && slot.kind === "booking")) {
              entry = slot;
            }
          }
        });
        return entry;
      };

      const buildBookingStartMap = () => {
        const map = {};
        blockedSlots.forEach((slot) => {
          if (!slot || slot.kind !== "booking") return;
          const key = slot.booking_id || slot.label || "";
          if (!key) return;
          const value = `${slot.date} ${slot.start}`;
          if (!map[key] || value < map[key]) {
            map[key] = value;
          }
        });
        return map;
      };

      let calendarView = "week";

      const setCalendarView = (view) => {
        calendarView = view;
        calendarViewButtons.forEach((btn) => {
          btn.setAttribute("aria-pressed", btn.dataset.calendarView === view ? "true" : "false");
        });
        calendarFields.forEach((field) => {
          field.hidden = field.dataset.calendarField !== view;
        });
        renderCalendarView();
      };

      const renderTimeGrid = (dates) => {
        const bookingStartMap = buildBookingStartMap();
        calendarGrid.innerHTML = "";
        calendarGrid.classList.remove("month");
        calendarGrid.style.gridTemplateColumns = `90px repeat(${dates.length}, minmax(90px, 1fr))`;

        const headCell = document.createElement("div");
        headCell.className = "calendar-cell calendar-head";
        headCell.textContent = "Time";
        calendarGrid.appendChild(headCell);

        dates.forEach((dateKey) => {
          const cell = document.createElement("div");
          cell.className = "calendar-cell calendar-head";
          cell.dataset.date = dateKey;
          cell.textContent = formatDayLabel(dateKey);
          calendarGrid.appendChild(cell);
        });

        SLOT_TIMES.forEach((timeValue) => {
          const timeCell = document.createElement("div");
          timeCell.className = "calendar-cell calendar-time";
          timeCell.textContent = timeValue;
          calendarGrid.appendChild(timeCell);

          dates.forEach((dateKey) => {
            const slotButton = document.createElement("button");
            slotButton.type = "button";
            slotButton.className = "calendar-cell calendar-slot";
            slotButton.dataset.date = dateKey;
            slotButton.dataset.time = timeValue;
            const entry = getSlotEntry(dateKey, timeValue);
            if (entry) {
              if (entry.kind === "booking") {
                slotButton.classList.add("booking");
                if (entry.booking_type === "outcall") {
                  slotButton.classList.add("outcall");
                }
                if (entry.booking_status === "paid") {
                  slotButton.classList.add("paid");
                }
                const key = entry.booking_id || entry.label || "";
                const startKey = key ? bookingStartMap[key] : "";
                if (key && startKey === `${entry.date} ${entry.start}`) {
                  slotButton.textContent = entry.label || "Booked";
                }
                const titleLabel = entry.label ? `${entry.label} - ` : "";
                slotButton.title = `${titleLabel}${entry.booking_type || "incall"} (${entry.booking_status || "paid"})`;
                slotButton.disabled = true;
              } else if (entry.kind === "template") {
                slotButton.classList.add("blocked");
                slotButton.classList.add("template");
                const citySuffix = entry.city ? ` (${entry.city})` : "";
                slotButton.title = (entry.reason || "Template block") + citySuffix;
              } else {
                slotButton.classList.add("blocked");
                slotButton.title = entry.reason || "Blocked";
              }
            } else if (isRecurringSlot(dateKey, timeValue)) {
              slotButton.classList.add("blocked");
              slotButton.classList.add("recurring");
              slotButton.title = "Recurring block";
            }
            slotButton.addEventListener("click", () => {
              if (entry && (entry.kind === "booking" || entry.kind === "template")) {
                return;
              }
              const start = timeValue;
              const end = minutesToTime(timeToMinutes(timeValue) + SLOT_MINUTES);
              const index = blockedSlots.findIndex(
                (slot) => slot.date === dateKey && slot.start === start && slot.end === end && slot.kind !== "booking"
              );
              if (index >= 0) {
                blockedSlots.splice(index, 1);
              } else {
                blockedSlots.push({ date: dateKey, start, end, reason: "", kind: "manual" });
              }
              renderBlockedSlots();
              renderCalendarView();
              queueAutoSave();
            });
            calendarGrid.appendChild(slotButton);
          });
        });
      };

      const renderWeekCalendar = () => {
        if (!calendarGrid || !calendarStart) return;
        const startKey = calendarStart.value || getDateKey(new Date());
        const dates = getWeekDates(startKey);
        renderTimeGrid(dates);
      };

      const renderDayCalendar = () => {
        if (!calendarGrid || !calendarDay) return;
        const dayKey = calendarDay.value || getDateKey(new Date());
        renderTimeGrid([dayKey]);
      };

      const getMonthGrid = (year, monthIndex) => {
        const first = new Date(Date.UTC(year, monthIndex, 1));
        const startDay = first.getUTCDay();
        const daysInMonth = new Date(Date.UTC(year, monthIndex + 1, 0)).getUTCDate();
        const totalCells = Math.ceil((startDay + daysInMonth) / 7) * 7;
        return Array.from({ length: totalCells }, (_, index) => {
          const day = index - startDay + 1;
          if (day < 1 || day > daysInMonth) return null;
          const month = String(monthIndex + 1).padStart(2, "0");
          const date = String(day).padStart(2, "0");
          return `${year}-${month}-${date}`;
        });
      };

      const getDaySummary = (dateKey) => {
        const bookingIds = new Set();
        const paidIds = new Set();
        let hasManual = false;
        blockedSlots.forEach((slot) => {
          if (!slot || slot.date !== dateKey) return;
          if (slot.kind === "booking") {
            const key = slot.booking_id || `${slot.label}-${slot.start}`;
            bookingIds.add(key);
            if (slot.booking_status === "paid") {
              paidIds.add(key);
            }
            return;
          }
          hasManual = true;
        });
        const weekdayIndex = getWeekdayIndex(dateKey);
        const hasRecurring = recurringBlocks.some((block) => {
          if (!block) return false;
          const days = Array.isArray(block.days) ? block.days : [];
          if (!days.includes(weekdayIndex)) return false;
          if (block.all_day) return true;
          const startMinutes = timeToMinutes(block.start);
          const endMinutes = timeToMinutes(block.end);
          return startMinutes !== null && endMinutes !== null;
        });
        return {
          bookings: bookingIds.size,
          paid: paidIds.size,
          manual: hasManual,
          recurring: hasRecurring,
        };
      };

      const renderMonthCalendar = () => {
        if (!calendarGrid || !calendarMonth) return;
        const monthValue = calendarMonth.value || getDateKey(new Date()).slice(0, 7);
        const [yearStr, monthStr] = monthValue.split("-");
        const year = Number(yearStr);
        const monthIndex = Number(monthStr) - 1;
        if (!year || Number.isNaN(monthIndex)) return;
        calendarGrid.innerHTML = "";
        calendarGrid.classList.add("month");

        const weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        weekdays.forEach((label) => {
          const head = document.createElement("div");
          head.className = "calendar-cell calendar-head";
          head.textContent = label;
          calendarGrid.appendChild(head);
        });

        const cells = getMonthGrid(year, monthIndex);
        cells.forEach((dateKey) => {
          const cell = document.createElement("div");
          cell.className = "month-cell";
          if (!dateKey) {
            cell.classList.add("muted");
            calendarGrid.appendChild(cell);
            return;
          }
          const dayNumber = Number(dateKey.split("-")[2]);
          const dayLabel = document.createElement("div");
          dayLabel.className = "month-day";
          dayLabel.textContent = String(dayNumber);
          cell.appendChild(dayLabel);

          const summary = getDaySummary(dateKey);
          const badgeWrap = document.createElement("div");
          badgeWrap.className = "month-badges";
          if (summary.bookings > 0) {
            const badge = document.createElement("span");
            badge.className = "month-badge booking";
            badge.textContent = `Bookings ${summary.bookings}`;
            badgeWrap.appendChild(badge);
          }
          if (summary.paid > 0) {
            const badge = document.createElement("span");
            badge.className = "month-badge paid";
            badge.textContent = `Paid ${summary.paid}`;
            badgeWrap.appendChild(badge);
          }
          if (summary.manual || summary.recurring) {
            const badge = document.createElement("span");
            badge.className = "month-badge blocked";
            badge.textContent = "Blocked";
            badgeWrap.appendChild(badge);
          }
          if (badgeWrap.childElementCount > 0) {
            cell.appendChild(badgeWrap);
          }
          cell.addEventListener("click", () => {
            if (calendarDay) {
              calendarDay.value = dateKey;
            }
            setCalendarView("day");
          });
          calendarGrid.appendChild(cell);
        });
      };

      const renderCalendarView = () => {
        if (!calendarGrid) return;
        if (calendarView === "day") {
          renderDayCalendar();
          return;
        }
        if (calendarView === "month") {
          renderMonthCalendar();
          return;
        }
        renderWeekCalendar();
      };

      const renderBlockedSlots = () => {
        if (!blockedList) return;
        const manualSlots = blockedSlots
          .map((slot, index) => ({ slot, index }))
          .filter(({ slot }) => slot && slot.kind === "manual");
        if (!manualSlots.length) {
          blockedList.textContent = "No manual blocked slots yet.";
          return;
        }
        blockedList.innerHTML = manualSlots
          .map(({ slot, index }) => {
            const reason = slot.reason ? ` - ${slot.reason}` : "";
            return `<div data-index="${index}">${slot.date} ${slot.start}-${slot.end}${reason} <button data-remove="${index}" class="btn ghost" type="button">${t("remove")}</button></div>`;
          })
          .join("");
        blockedList.querySelectorAll("button[data-remove]").forEach((btn) => {
          btn.addEventListener("click", () => {
            const idx = Number(btn.dataset.remove);
            if (Number.isNaN(idx)) return;
            blockedSlots = blockedSlots.filter((_, i) => i !== idx);
            renderBlockedSlots();
            renderCalendarView();
            queueAutoSave();
          });
        });
      };

      const getSelectedRecurringDays = () => {
        if (!recurringDays) return [];
        return Array.from(recurringDays.querySelectorAll("input[type=\"checkbox\"]:checked"))
          .map((input) => Number(input.value))
          .filter((value) => !Number.isNaN(value));
      };

      const formatRecurringDays = (days) => {
        const labels = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        return (Array.isArray(days) ? days : [])
          .map((day) => labels[day])
          .filter(Boolean)
          .join(", ");
      };

      const renderRecurringList = () => {
        if (!recurringList) return;
        if (!recurringBlocks.length) {
          recurringList.textContent = "No recurring blocks yet.";
          return;
        }
        recurringList.innerHTML = recurringBlocks
          .map((block, index) => {
            const daysLabel = formatRecurringDays(block.days);
            const timeLabel = block.all_day ? "All day" : `${block.start || ""}-${block.end || ""}`;
            const reason = block.reason ? ` - ${block.reason}` : "";
            return `<div data-index=\"${index}\">${daysLabel} | ${timeLabel}${reason} <button data-remove=\"${index}\" class=\"btn ghost\" type=\"button\">${t("remove")}</button></div>`;
          })
          .join("");
        recurringList.querySelectorAll("button[data-remove]").forEach((btn) => {
          btn.addEventListener("click", () => {
            const idx = Number(btn.dataset.remove);
            if (Number.isNaN(idx)) return;
            recurringBlocks = recurringBlocks.filter((_, i) => i !== idx);
            renderRecurringList();
            renderCalendarView();
            queueAutoSave();
          });
        });
      };

      const setBlockedListVisible = (visible) => {
        if (!blockedList || !toggleBlockedListBtn) return;
        blockedList.classList.toggle("hidden", !visible);
        toggleBlockedListBtn.textContent = visible ? "Hide blocked slots" : "Show blocked slots";
      };

      const loadAvailability = async () => {
        availabilityStatus.textContent = "";
        try {
          const response = await fetch("../api/availability.php", { cache: "no-store" });
          if (!response.ok) throw new Error("load");
          const data = await response.json();
          tourCityInput.value = data.tour_city || "";
          tourTzSelect.value = data.tour_timezone || TIMEZONES[0];
          bufferInput.value = data.buffer_minutes || 30;
          blockedSlots = normalizeBlockedSlots(data.blocked);
          recurringBlocks = Array.isArray(data.recurring) ? data.recurring : [];
          citySchedules = (Array.isArray(data.city_schedules) ? data.city_schedules : [])
            .map((entry) => normalizeCitySchedule(entry))
            .filter((entry) => entry.city && entry.start && entry.end);
          if (touringStops.length) {
            syncCitySchedulesWithTouring();
          } else {
            renderCityScheduleWizard();
            applyCityTemplateBlocks({ announce: false });
          }
          renderRecurringList();
          renderBlockedSlots();
          const todayKey = getDateKey(new Date());
          if (calendarStart) {
            calendarStart.value = todayKey;
          }
          if (calendarDay) {
            calendarDay.value = todayKey;
          }
          if (calendarMonth) {
            calendarMonth.value = todayKey.slice(0, 7);
          }
          renderCalendarView();
        } catch (_error) {
          availabilityStatus.textContent = t("unable_load_city_schedule");
        }
      };

      const saveAvailability = async () => {
        availabilityStatus.textContent = "";
        const key = getKey();
        if (!key) {
          availabilityStatus.textContent = t("admin_key_required");
          return;
        }
        applyCityTemplateBlocks({ announce: false });
        const cityPayload = readCitySchedulePayload();
        const firstCity = cityPayload[0] || null;
        const payload = {
          tour_city: firstCity?.city || tourCityInput.value.trim(),
          tour_timezone: firstCity?.timezone || tourTzSelect.value,
          buffer_minutes: Number((firstCity?.buffer_minutes ?? bufferInput.value) || 0),
          availability_mode: "open",
          blocked: blockedSlots,
          recurring: recurringBlocks,
          city_schedules: cityPayload,
        };
        try {
          const response = await fetch("../api/admin/availability.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Admin-Key": key,
            },
            body: JSON.stringify(payload),
          });
          const result = await response.json();
          if (!response.ok) throw new Error(result.error || "save");
          availabilityStatus.textContent = t("city_schedule_saved");
          if (cityScheduleStatus) {
            cityScheduleStatus.textContent = t("city_schedules_saved");
          }
        } catch (_error) {
          availabilityStatus.textContent = t("failed_save_city_schedule");
        }
      };

      const queueAutoSave = (message = t("unsaved_changes")) => {
        availabilityStatus.textContent = message;
      };

      const resolveMediaSrc = (value) => {
        const raw = String(value || "").trim();
        if (!raw) return "";
        if (/^https?:\/\//i.test(raw) || raw.startsWith("/")) {
          return raw;
        }
        return `/${raw.replace(/^\/+/, "")}`;
      };

      const createField = (labelText, type, value = "", placeholder = "") => {
        const wrapper = document.createElement("div");
        wrapper.className = "field";
        const label = document.createElement("label");
        label.textContent = labelText;
        const input = document.createElement("input");
        input.type = type;
        input.value = value;
        if (placeholder) input.placeholder = placeholder;
        wrapper.appendChild(label);
        wrapper.appendChild(input);
        return { wrapper, input };
      };

      const createTourRow = (entry = {}) => {
        const row = document.createElement("div");
        row.className = "editor-row";
        row.dataset.tourRow = "1";
        const startField = createField(t("start_date"), "date", entry.start || "");
        const endField = createField(t("end_date"), "date", entry.end || "");
        const cityField = createField(t("city_field"), "text", entry.city || "", t("city_name_placeholder"));
        const removeBtn = createActionButton(t("remove"), () => row.remove(), "btn ghost");
        row.appendChild(startField.wrapper);
        row.appendChild(endField.wrapper);
        row.appendChild(cityField.wrapper);
        row.appendChild(removeBtn);
        return row;
      };

      const createGalleryRow = (item = {}) => {
        const row = document.createElement("div");
        row.className = "editor-row gallery";
        row.dataset.galleryRow = "1";
        const srcField = createField(t("photo_path"), "text", item.src || "", "/photos/heidi15.jpg");
        const altField = createField(t("alt_text"), "text", item.alt || "", t("short_description"));
        const previewWrap = document.createElement("div");
        previewWrap.className = "gallery-preview";
        const preview = document.createElement("img");
        preview.className = "gallery-thumb";
        preview.alt = item.alt || t("preview");
        preview.src = resolveMediaSrc(item.src || "");
        preview.addEventListener("error", () => {
          preview.classList.add("broken");
        });
        previewWrap.appendChild(preview);
        srcField.input.addEventListener("input", () => {
          preview.classList.remove("broken");
          preview.src = resolveMediaSrc(srcField.input.value.trim());
        });
        altField.input.addEventListener("input", () => {
          preview.alt = altField.input.value.trim() || t("preview");
        });
        const removeBtn = createActionButton(t("remove"), () => row.remove(), "btn ghost");
        row.appendChild(srcField.wrapper);
        row.appendChild(altField.wrapper);
        row.appendChild(previewWrap);
        row.appendChild(removeBtn);
        return row;
      };

      const renderTourSchedule = (entries) => {
        if (!tourScheduleList) return;
        tourScheduleList.innerHTML = "";
        const list = Array.isArray(entries) ? entries : [];
        if (!list.length) {
          tourScheduleList.appendChild(createTourRow());
          return;
        }
        list.forEach((entry) => {
          tourScheduleList.appendChild(createTourRow(entry));
        });
      };

      const renderGallery = (items) => {
        if (!galleryList) return;
        galleryList.innerHTML = "";
        const list = Array.isArray(items) ? items : [];
        if (!list.length) {
          galleryList.appendChild(createGalleryRow());
          return;
        }
        list.forEach((item) => {
          galleryList.appendChild(createGalleryRow(item));
        });
      };

      const readTourScheduleFromUI = () => {
        if (!tourScheduleList) return [];
        return Array.from(tourScheduleList.querySelectorAll("[data-tour-row]"))
          .map((row) => {
            const inputs = row.querySelectorAll("input");
            const start = inputs[0]?.value || "";
            const end = inputs[1]?.value || "";
            const city = inputs[2]?.value?.trim() || "";
            return { start, end, city };
          })
          .filter((entry) => entry.start && entry.end && entry.city);
      };

      const normalizeTouringEntries = (entries) =>
        (Array.isArray(entries) ? entries : [])
          .map((entry) => ({
            start: String(entry?.start || "").trim(),
            end: String(entry?.end || "").trim(),
            city: String(entry?.city || "").trim(),
          }))
          .filter(
            (entry) =>
              /^\d{4}-\d{2}-\d{2}$/.test(entry.start) &&
              /^\d{4}-\d{2}-\d{2}$/.test(entry.end) &&
              entry.city &&
              entry.start <= entry.end
          )
          .sort((a, b) => (a.start + a.city).localeCompare(b.start + b.city));

      const readCitySchedulePayload = () =>
        citySchedules.map((schedule) => ({
          city: schedule.city,
          start: schedule.start,
          end: schedule.end,
          timezone: schedule.timezone,
          ready_start: schedule.ready_start,
          buffer_minutes: Number(schedule.buffer_minutes || 0),
          has_sleep: !!schedule.has_sleep,
          sleep_days: Array.isArray(schedule.sleep_days) ? schedule.sleep_days : [],
          sleep_start: schedule.sleep_start,
          sleep_end: schedule.sleep_end,
          has_break: !!schedule.has_break,
          break_days: Array.isArray(schedule.break_days) ? schedule.break_days : [],
          break_start: schedule.break_start,
          break_end: schedule.break_end,
          leave_day_end: schedule.leave_day_end,
        }));

      const updateCitySchedule = (scheduleId, patch = {}, { applyTemplates = true } = {}) => {
        citySchedules = citySchedules.map((schedule) => {
          if (schedule.id !== scheduleId) {
            return schedule;
          }
          return normalizeCitySchedule({ ...schedule, ...patch });
        });
        if (applyTemplates) {
          applyCityTemplateBlocks({ announce: false });
        }
      };

      const getWizardValidationMessage = (schedule, step) => {
        if (step === 0) {
          if (!isValidTime(schedule.ready_start)) {
            return t("pick_valid_ready_time");
          }
          return "";
        }
        if (step === 1) {
          const buffer = Number(schedule.buffer_minutes);
          if (!Number.isFinite(buffer) || buffer < 0) {
            return t("buffer_invalid");
          }
          return "";
        }
        if (step === 2) {
          if (schedule.has_sleep) {
            if (!isValidTime(schedule.sleep_start) || !isValidTime(schedule.sleep_end)) {
              return t("pick_sleep_times");
            }
            if (!Array.isArray(schedule.sleep_days) || schedule.sleep_days.length < 1) {
              return t("pick_sleep_days");
            }
          }
          return "";
        }
        if (step === 3) {
          if (schedule.has_break) {
            if (!isValidTime(schedule.break_start) || !isValidTime(schedule.break_end)) {
              return t("pick_break_times");
            }
            if (!Array.isArray(schedule.break_days) || schedule.break_days.length < 1) {
              return t("pick_break_days");
            }
          }
          return "";
        }
        if (step === 4) {
          if (!isValidTime(schedule.leave_day_end)) {
            return t("pick_leave_day_end");
          }
          return "";
        }
        return "";
      };

      const createCityWizardCard = (schedule) => {
        const card = document.createElement("div");
        card.className = "city-wizard-card";
        const cardKey = schedule.id.replace(/[^a-z0-9]/gi, "_");
        const scheduleDates = getDateRangeKeys(schedule.start, schedule.end);
        const renderDayChoices = (fieldName, selectedDays) =>
          scheduleDates
            .map((dateKey) => {
              const checked = Array.isArray(selectedDays) && selectedDays.includes(dateKey) ? "checked" : "";
              return `<label class="city-day-chip"><input type="checkbox" data-day-field="${fieldName}" value="${dateKey}" ${checked} /> ${formatDateLabel(dateKey)}</label>`;
            })
            .join("");

        const header = document.createElement("div");
        header.className = "city-wizard-head";

        const titleWrap = document.createElement("div");
        const title = document.createElement("h3");
        title.className = "city-wizard-title";
        title.textContent = schedule.city || t("tour_stop");
        const dateLine = document.createElement("div");
        dateLine.className = "city-wizard-date";
        dateLine.textContent = `${formatDateLabel(schedule.start)} ${t("date_to")} ${formatDateLabel(schedule.end)}`;
        const timezoneLine = document.createElement("div");
        timezoneLine.className = "city-wizard-zone";
        titleWrap.appendChild(title);
        titleWrap.appendChild(dateLine);
        titleWrap.appendChild(timezoneLine);

        const stepBadge = document.createElement("div");
        stepBadge.className = "city-wizard-step";
        header.appendChild(titleWrap);
        header.appendChild(stepBadge);

        const body = document.createElement("div");
        body.className = "city-wizard-body";

        const stepPanels = [0, 1, 2, 3, 4].map(() => {
          const panel = document.createElement("div");
          panel.className = "city-wizard-hidden";
          body.appendChild(panel);
          return panel;
        });

        const step1 = stepPanels[0];
        step1.innerHTML = `
          <p class="city-wizard-question">${t("q_ready_time")}</p>
          <input type="time" data-field="ready_start" value="${schedule.ready_start}" />
        `;

        const step2 = stepPanels[1];
        step2.innerHTML = `
          <p class="city-wizard-question">${t("q_buffer")}</p>
          <input type="number" min="0" max="240" step="5" data-field="buffer_minutes" value="${schedule.buffer_minutes}" />
        `;

        const step3 = stepPanels[2];
        step3.innerHTML = `
          <p class="city-wizard-question">${t("q_sleep")}</p>
          <div class="row">
            <label><input type="radio" name="sleep_${cardKey}" value="yes" ${schedule.has_sleep ? "checked" : ""} /> ${t("yes")}</label>
            <label><input type="radio" name="sleep_${cardKey}" value="no" ${schedule.has_sleep ? "" : "checked"} /> ${t("no")}</label>
          </div>
          <div class="city-wizard-row" data-sleep-fields>
            <input type="time" data-field="sleep_start" value="${schedule.sleep_start}" />
            <input type="time" data-field="sleep_end" value="${schedule.sleep_end}" />
          </div>
          <div data-sleep-day-fields>
            <label>${t("which_days")}</label>
            <div class="city-day-picks">
              ${renderDayChoices("sleep_days", schedule.sleep_days)}
            </div>
          </div>
        `;

        const step4 = stepPanels[3];
        step4.innerHTML = `
          <p class="city-wizard-question">${t("q_breaks")}</p>
          <div class="row">
            <label><input type="radio" name="break_${cardKey}" value="yes" ${schedule.has_break ? "checked" : ""} /> ${t("yes")}</label>
            <label><input type="radio" name="break_${cardKey}" value="no" ${schedule.has_break ? "" : "checked"} /> ${t("no")}</label>
          </div>
          <div class="city-wizard-row" data-break-fields>
            <input type="time" data-field="break_start" value="${schedule.break_start}" />
            <input type="time" data-field="break_end" value="${schedule.break_end}" />
          </div>
          <div data-break-day-fields>
            <label>${t("which_days")}</label>
            <div class="city-day-picks">
              ${renderDayChoices("break_days", schedule.break_days)}
            </div>
          </div>
        `;

        const step5 = stepPanels[4];
        step5.innerHTML = `
          <p class="city-wizard-question">${t("q_leave_time")}</p>
          <input type="time" data-field="leave_day_end" value="${schedule.leave_day_end}" />
        `;

        const nav = document.createElement("div");
        nav.className = "city-wizard-nav";
        const backBtn = createActionButton(t("back"), () => {}, "btn ghost");
        const nextBtn = createActionButton(t("next"), () => {}, "btn secondary");
        nav.appendChild(backBtn);
        nav.appendChild(nextBtn);
        body.appendChild(nav);

        const sleepFieldsWrap = step3.querySelector("[data-sleep-fields]");
        const sleepDayFieldsWrap = step3.querySelector("[data-sleep-day-fields]");
        const breakFieldsWrap = step4.querySelector("[data-break-fields]");
        const breakDayFieldsWrap = step4.querySelector("[data-break-day-fields]");

        const refreshSleepFields = () => {
          const sleepSelected = step3.querySelector(`input[name="sleep_${cardKey}"]:checked`);
          const hasSleep = sleepSelected?.value === "yes";
          sleepFieldsWrap.classList.toggle("city-wizard-hidden", !hasSleep);
          sleepDayFieldsWrap.classList.toggle("city-wizard-hidden", !hasSleep);
          const current = citySchedules.find((item) => item.id === schedule.id) || schedule;
          const sleepDays =
            hasSleep && Array.isArray(current.sleep_days) && current.sleep_days.length
              ? current.sleep_days
              : hasSleep
                ? scheduleDates.slice()
                : [];
          updateCitySchedule(schedule.id, { has_sleep: hasSleep, sleep_days: sleepDays }, { applyTemplates: false });
        };

        const refreshBreakFields = () => {
          const breakSelected = step4.querySelector(`input[name="break_${cardKey}"]:checked`);
          const hasBreak = breakSelected?.value === "yes";
          breakFieldsWrap.classList.toggle("city-wizard-hidden", !hasBreak);
          breakDayFieldsWrap.classList.toggle("city-wizard-hidden", !hasBreak);
          const current = citySchedules.find((item) => item.id === schedule.id) || schedule;
          const breakDays =
            hasBreak && Array.isArray(current.break_days) && current.break_days.length
              ? current.break_days
              : hasBreak
                ? scheduleDates.slice()
                : [];
          updateCitySchedule(schedule.id, { has_break: hasBreak, break_days: breakDays }, { applyTemplates: false });
        };

        const syncStepUi = () => {
          const current = citySchedules.find((item) => item.id === schedule.id) || schedule;
          const step = Number(current._step || 0);
          stepPanels.forEach((panel, index) => {
            panel.classList.toggle("city-wizard-hidden", index !== step);
          });
          timezoneLine.textContent = `${t("timezone")}: ${current.timezone || t("not_set")}`;
          stepBadge.textContent = t("step", { current: step + 1, total: 5 });
          backBtn.disabled = step <= 0;
          nextBtn.textContent = step >= 4 ? t("done") : t("next");
          refreshSleepFields();
          refreshBreakFields();
        };

        const captureInputs = () => {
          const patch = {};
          card.querySelectorAll("[data-field]").forEach((input) => {
            const field = input.dataset.field;
            if (!field) return;
            if (input.type === "number") {
              patch[field] = Math.max(0, Math.min(240, Number(input.value || 0) || 0));
            } else {
              patch[field] = input.value;
            }
          });
          patch.sleep_days = Array.from(
            card.querySelectorAll('input[data-day-field="sleep_days"]:checked')
          ).map((input) => input.value);
          patch.break_days = Array.from(
            card.querySelectorAll('input[data-day-field="break_days"]:checked')
          ).map((input) => input.value);
          updateCitySchedule(schedule.id, patch, { applyTemplates: false });
        };

        card.querySelectorAll("[data-field]").forEach((input) => {
          input.addEventListener("input", () => {
            captureInputs();
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("templates_changed"));
          });
          input.addEventListener("change", () => {
            captureInputs();
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("templates_changed"));
          });
        });

        card.querySelectorAll("[data-day-field]").forEach((input) => {
          input.addEventListener("change", () => {
            captureInputs();
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("templates_changed"));
          });
        });

        step3.querySelectorAll(`input[name="sleep_${cardKey}"]`).forEach((input) => {
          input.addEventListener("change", () => {
            refreshSleepFields();
            captureInputs();
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("templates_changed"));
          });
        });
        step4.querySelectorAll(`input[name="break_${cardKey}"]`).forEach((input) => {
          input.addEventListener("change", () => {
            refreshBreakFields();
            captureInputs();
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("templates_changed"));
          });
        });

        backBtn.addEventListener("click", () => {
          const current = citySchedules.find((item) => item.id === schedule.id) || schedule;
          if (current._step <= 0) return;
          updateCitySchedule(schedule.id, { _step: current._step - 1 }, { applyTemplates: false });
          syncStepUi();
        });

        nextBtn.addEventListener("click", async () => {
          captureInputs();
          const current = citySchedules.find((item) => item.id === schedule.id) || schedule;
          const validationMessage = getWizardValidationMessage(current, current._step || 0);
          if (validationMessage) {
            if (cityScheduleStatus) {
              cityScheduleStatus.textContent = `${current.city}: ${validationMessage}`;
            }
            return;
          }
          if (current._step >= 4) {
            applyCityTemplateBlocks({ announce: false });
            queueAutoSave(t("saving_city_schedule"));
            await saveAvailability();
            if (cityScheduleStatus) {
              cityScheduleStatus.textContent = t("city_updated_applied", { city: current.city });
            }
            return;
          }
          updateCitySchedule(schedule.id, { _step: current._step + 1 }, { applyTemplates: false });
          syncStepUi();
        });

        card.appendChild(header);
        card.appendChild(body);
        syncStepUi();
        return card;
      };

      const renderCityScheduleWizard = () => {
        if (!cityScheduleWizard) return;
        cityScheduleWizard.innerHTML = "";
        if (!citySchedules.length) {
          cityScheduleWizard.innerHTML = `<p class="hint">${t("add_tour_first")}</p>`;
          return;
        }
        citySchedules.forEach((schedule) => {
          cityScheduleWizard.appendChild(createCityWizardCard(schedule));
        });
      };

      const syncCitySchedulesWithTouring = () => {
        const existingById = new Map(citySchedules.map((schedule) => [schedule.id, schedule]));
        citySchedules = touringStops.map((stop) => {
          const id = makeScheduleId(stop);
          const existing = existingById.get(id) || {};
          return normalizeCitySchedule({ ...stop, ...existing });
        });
        if (citySchedules.length) {
          const first = citySchedules[0];
          if (tourCityInput) {
            tourCityInput.value = first.city;
          }
          if (tourTzSelect) {
            tourTzSelect.value = first.timezone || TIMEZONES[0];
          }
          if (bufferInput) {
            bufferInput.value = Number(first.buffer_minutes || 0);
          }
        }
        renderCityScheduleWizard();
        applyCityTemplateBlocks({ announce: false });
      };

      const readGalleryFromUI = () => {
        if (!galleryList) return [];
        return Array.from(galleryList.querySelectorAll("[data-gallery-row]"))
          .map((row) => {
            const inputs = row.querySelectorAll("input");
            const src = inputs[0]?.value?.trim() || "";
            const alt = inputs[1]?.value?.trim() || "";
            return { src, alt };
          })
          .filter((item) => item.src);
      };

      const loadTourSchedule = async () => {
        if (!tourScheduleStatus) return;
        tourScheduleStatus.textContent = "";
        const key = getKey();
        if (!key) {
          tourScheduleStatus.textContent = t("admin_key_required");
          return;
        }
        try {
          const response = await fetch("../api/admin/tour-schedule.php", {
            headers: { ...headersWithKey() },
            cache: "no-store",
          });
          const data = await response.json();
          if (!response.ok) throw new Error(data.error || "load");
          touringStops = normalizeTouringEntries(data.touring || []);
          renderTourSchedule(touringStops);
          syncCitySchedulesWithTouring();
        } catch (_error) {
          tourScheduleStatus.textContent = t("failed_load_tour_schedule");
        }
      };

      const saveTourSchedule = async () => {
        if (!tourScheduleStatus) return;
        tourScheduleStatus.textContent = "";
        const key = getKey();
        if (!key) {
          tourScheduleStatus.textContent = t("admin_key_required");
          return;
        }
        const entries = readTourScheduleFromUI();
        if (!entries.length) {
          tourScheduleStatus.textContent = t("add_entry_min");
          return;
        }
        try {
          const response = await fetch("../api/admin/tour-schedule.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Admin-Key": key,
            },
            body: JSON.stringify({ touring: entries }),
          });
          const result = await response.json();
          if (!response.ok) throw new Error(result.error || "save");
          touringStops = normalizeTouringEntries(result.touring || entries);
          renderTourSchedule(touringStops);
          syncCitySchedulesWithTouring();
          tourScheduleStatus.textContent = t("tour_schedule_saved");
          queueAutoSave(t("tour_dates_changed"));
        } catch (_error) {
          tourScheduleStatus.textContent = t("failed_save_tour_schedule");
        }
      };

      const loadGallery = async () => {
        if (!galleryStatus) return;
        galleryStatus.textContent = "";
        const key = getKey();
        if (!key) {
          galleryStatus.textContent = t("admin_key_required");
          return;
        }
        try {
          const response = await fetch("../api/admin/gallery.php", {
            headers: { ...headersWithKey() },
            cache: "no-store",
          });
          const data = await response.json();
          if (!response.ok) throw new Error(data.error || "load");
          renderGallery(data.items || []);
        } catch (_error) {
          galleryStatus.textContent = t("failed_load_eye_candy");
        }
      };

      const saveGallery = async () => {
        if (!galleryStatus) return;
        galleryStatus.textContent = "";
        const key = getKey();
        if (!key) {
          galleryStatus.textContent = t("admin_key_required");
          return;
        }
        const items = readGalleryFromUI();
        if (!items.length) {
          galleryStatus.textContent = t("add_photo_min");
          return;
        }
        try {
          const response = await fetch("../api/admin/gallery.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Admin-Key": key,
            },
            body: JSON.stringify({ items }),
          });
          const result = await response.json();
          if (!response.ok) throw new Error(result.error || "save");
          renderGallery(result.items || items);
          galleryStatus.textContent = t("eye_candy_saved");
        } catch (_error) {
          galleryStatus.textContent = t("failed_save_eye_candy");
        }
      };


      const createActionButton = (label, onClick, className = "btn ghost") => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = className;
        btn.textContent = label;
        btn.addEventListener("click", onClick);
        return btn;
      };

      const formatLine = (label, value) =>
        value ? `<div class="meta"><strong>${label}:</strong> ${value}</div>` : "";

      const formatArray = (list) => (Array.isArray(list) ? list.filter(Boolean).join(", ") : "");

      const formatPaymentMethod = (value) => {
        const normalized = String(value || "").toLowerCase();
        if (normalized === "etransfer" || normalized === "interac") return "Interac e-Transfer";
        if (normalized === "wise") return "Wise";
        if (normalized === "usdc") return "USDC";
        if (normalized === "ltc" || normalized === "litecoin") return "Litecoin";
        if (normalized === "btc") return "Bitcoin";
        if (normalized === "paypal") return "PayPal";
        return value || "";
      };

      const formatCalendarStamp = (dateValue, timeValue, addMinutes = 0) => {
        const [year, month, day] = String(dateValue || "").split("-").map((value) => Number(value));
        const [hour, minute] = String(timeValue || "").split(":").map((value) => Number(value));
        if (!year || !month || !day || Number.isNaN(hour) || Number.isNaN(minute)) {
          return "";
        }
        const stamp = new Date(Date.UTC(year, month - 1, day, hour, minute));
        stamp.setUTCMinutes(stamp.getUTCMinutes() + addMinutes);
        const pad = (value) => String(value).padStart(2, "0");
        return `${stamp.getUTCFullYear()}${pad(stamp.getUTCMonth() + 1)}${pad(stamp.getUTCDate())}T${pad(
          stamp.getUTCHours()
        )}${pad(stamp.getUTCMinutes())}00`;
      };

      const buildGoogleCalendarUrl = (item) => {
        const dateValue = item.preferred_date || "";
        const timeValue = item.preferred_time || "";
        if (!dateValue || !timeValue) return "";
        const durationHours = Number(item.duration_hours || 0);
        const minutes = durationHours > 0 ? Math.round(durationHours * 60) : 60;
        const start = formatCalendarStamp(dateValue, timeValue, 0);
        const end = formatCalendarStamp(dateValue, timeValue, minutes);
        if (!start || !end) return "";
        const title = encodeURIComponent(`Booking: ${item.name || "Client"}`);
        const details = encodeURIComponent(`Booking request ${item.id || ""}`);
        const location = encodeURIComponent(item.city || "");
        const tz = encodeURIComponent(item.tour_timezone || "America/Toronto");
        return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&details=${details}&location=${location}&dates=${start}/${end}&ctz=${tz}`;
      };

      const buildIcsTimestamp = (dateValue, timeValue, addMinutes = 0) => {
        const [year, month, day] = String(dateValue || "").split("-").map((value) => Number(value));
        const [hour, minute] = String(timeValue || "").split(":").map((value) => Number(value));
        if (!year || !month || !day || Number.isNaN(hour) || Number.isNaN(minute)) return "";
        const date = new Date(Date.UTC(year, month - 1, day, hour, minute));
        date.setUTCMinutes(date.getUTCMinutes() + addMinutes);
        const pad = (value) => String(value).padStart(2, "0");
        return `${date.getUTCFullYear()}${pad(date.getUTCMonth() + 1)}${pad(date.getUTCDate())}T${pad(
          date.getUTCHours()
        )}${pad(date.getUTCMinutes())}00Z`;
      };

      const downloadSamsungIcs = (item) => {
        const dateValue = String(item?.preferred_date || "");
        const timeValue = String(item?.preferred_time || "");
        if (!dateValue || !timeValue) return;
        const durationHours = Number(item?.duration_hours || 0);
        const durationMinutes = durationHours > 0 ? Math.round(durationHours * 60) : 60;
        const start = buildIcsTimestamp(dateValue, timeValue, 0);
        const end = buildIcsTimestamp(dateValue, timeValue, durationMinutes);
        if (!start || !end) return;

        const uid = `${item?.id || Date.now()}@bombacloud`;
        const summary = `Booking: ${item?.name || "Client"}`;
        const location = String(item?.city || "");
        const description = `Booking request ${item?.id || ""}`;
        const ics = [
          "BEGIN:VCALENDAR",
          "VERSION:2.0",
          "PRODID:-//BombaCLOUD//Booking//EN",
          "BEGIN:VEVENT",
          `UID:${uid}`,
          `DTSTAMP:${buildIcsTimestamp(dateValue, timeValue, 0)}`,
          `DTSTART:${start}`,
          `DTEND:${end}`,
          `SUMMARY:${summary.replace(/\n/g, " ")}`,
          `LOCATION:${location.replace(/\n/g, " ")}`,
          `DESCRIPTION:${description.replace(/\n/g, " ")}`,
          "END:VEVENT",
          "END:VCALENDAR",
          "",
        ].join("\r\n");

        const blob = new Blob([ics], { type: "text/calendar;charset=utf-8" });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement("a");
        const safeId = String(item?.id || "booking").replace(/[^a-zA-Z0-9_-]/g, "");
        link.href = url;
        link.download = `booking-${safeId}.ics`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
      };

      const normalizeStatus = (item) => {
        const rawStatus = String(item?.status || "pending").toLowerCase();
        const rawPayment = String(item?.payment_status || "").toLowerCase();
        const status = rawStatus === "paid" ? "accepted" : rawStatus;
        const paymentStatus = rawPayment || (rawStatus === "paid" ? "paid" : "");
        return { status, paymentStatus };
      };

      const updateStatus = async (id, status, reason = "") => {
        const key = getKey();
        if (!key) {
          requestsStatus.textContent = t("admin_key_required");
          return;
        }
        requestsStatus.textContent = "";
        try {
          const response = await fetch("../api/admin/status.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Admin-Key": key,
            },
            body: JSON.stringify({ id, status, reason }),
          });
          const result = await response.json();
          if (!response.ok) throw new Error(result.error || "status");
          requestsStatus.textContent = t("status_updated");
          await loadRequests();
          await loadAvailability();
        } catch (_error) {
          requestsStatus.textContent = t("failed_update_status");
        }
      };

      const getDownloadFilename = (response, fallback) => {
        const disposition = response.headers.get("Content-Disposition") || "";
        const match = disposition.match(/filename="?([^\";]+)"?/i);
        return match ? match[1] : fallback;
      };

      const downloadRequests = async (format) => {
        const key = getKey();
        if (!key) {
          requestsStatus.textContent = t("admin_key_required");
          return;
        }
        requestsStatus.textContent = "";
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
          requestsStatus.textContent = t("download_ready");
        } catch (_error) {
          requestsStatus.textContent = t("failed_download");
        }
      };

      const loadRequests = async () => {
        requestsStatus.textContent = "";
        requestsList.innerHTML = "";
        const key = getKey();
        if (!key) {
          requestsStatus.textContent = t("admin_key_required");
          return;
        }
        try {
          const response = await fetch("../api/admin/requests.php", {
            headers: { ...headersWithKey() },
          });
          const data = await response.json();
          if (!response.ok) throw new Error(data.error || "load");
          const requests = Array.isArray(data.requests) ? data.requests : [];
          const filterValue = statusFilter.value;
          const filtered = requests.filter((item) => {
            const { status, paymentStatus } = normalizeStatus(item);
            if (filterValue === "all") {
              return status !== "declined";
            }
            if (filterValue === "paid") {
              return paymentStatus === "paid";
            }
            if (filterValue === "accepted") {
              return status === "accepted";
            }
            return status === filterValue;
          });
          filtered
            .slice()
            .sort((a, b) => (b.created_at || "").localeCompare(a.created_at || ""))
            .forEach((item) => {
              const { status, paymentStatus } = normalizeStatus(item);
              const card = document.createElement("div");
              card.className = "request-card";
              const badgeClass = `badge ${status}`;
              const followupCities = formatArray(item.followup_cities);
              const percentLabel = item.deposit_percent ? `${item.deposit_percent}%` : "";
              let depositLabel = "";
              if (item.deposit_amount !== undefined && item.deposit_amount !== null) {
                depositLabel = `${item.deposit_amount}${item.deposit_currency ? " " + item.deposit_currency : ""}`;
                if (percentLabel) {
                  depositLabel += ` (${percentLabel})`;
                }
              } else if (percentLabel) {
                depositLabel = percentLabel;
              }
              const channelLabel =
                String(item.contact_channel || "").toLowerCase() === "phone"
                  ? t("phone_label").toLowerCase()
                  : t("email_label").toLowerCase();
              const followupInfo =
                item.contact_followup === "yes"
                  ? `${t("follow_up")}: ${channelLabel} (${followupCities || t("no_city")})`
                  : `${t("follow_up")}: ${t("follow_up_no")}`;
              card.innerHTML = `
                <div class="request-header">
                  <div><strong>${item.name || t("unknown")}</strong></div>
                  <div class="request-badges">
                    <span class="${badgeClass}">${statusLabel(status)}</span>
                    ${paymentStatus === "paid" ? `<span class="badge paid">${t("paid")}</span>` : ""}
                  </div>
                </div>
                ${formatLine(t("email_label"), item.email)}
                ${formatLine(t("phone_label"), item.phone)}
                ${formatLine(t("city_label"), item.city)}
                ${formatLine(t("type_label"), item.booking_type)}
                ${formatLine(t("outcall_address"), item.outcall_address)}
                ${formatLine(t("experience"), item.experience)}
                ${formatLine(t("duration"), item.duration_label)}
                ${formatLine(t("preferred"), `${item.preferred_date || ""} ${item.preferred_time || ""}`)}
                ${formatLine(t("client_tz"), item.client_timezone)}
                ${formatLine(t("tour_tz"), item.tour_timezone)}
                ${formatLine(t("deposit"), depositLabel)}
                ${formatLine(t("payment_status"), paymentStatus === "paid" ? t("paid") : "")}
                ${formatLine(t("payment_method"), formatPaymentMethod(item.payment_method))}
                ${formatLine(t("notes"), item.notes)}
                <div class="meta"><strong>${t("decline_reason")}:</strong> <input class="decline-reason" type="text" placeholder="${t("reason")}" value="${item.decline_reason || ""}" /></div>
                ${formatLine(t("blacklist_reason"), item.blacklist_reason)}
                ${formatLine(t("follow_up"), followupInfo)}
                ${formatLine(t("payment_details"), item.payment_link)}
                ${formatLine(t("created"), item.created_at)}
                ${formatLine(t("updated"), item.updated_at)}
                ${formatLine(t("email_sent"), item.payment_email_sent_at)}
              `;
              const declineInput = card.querySelector(".decline-reason");
              const actions = document.createElement("div");
              actions.className = "actions";
              if (status === "pending") {
                actions.appendChild(
                  createActionButton(t("action_accept"), () => updateStatus(item.id, "accepted"), "btn")
                );
                actions.appendChild(
                  createActionButton(
                    t("action_blacklist"),
                    () => updateStatus(item.id, "blacklisted", declineInput ? declineInput.value.trim() : ""),
                    "btn secondary"
                  )
                );
              }
              if (paymentStatus !== "paid" && status !== "blacklisted") {
                actions.appendChild(
                  createActionButton(t("action_mark_paid"), () => updateStatus(item.id, "paid"), "btn")
                );
              }
              if (status !== "blacklisted") {
                actions.appendChild(
                  createActionButton(
                    t("action_decline"),
                    () =>
                      updateStatus(item.id, "declined", declineInput ? declineInput.value.trim() : ""),
                    "btn secondary"
                  )
                );
                actions.appendChild(
                  createActionButton(t("action_cancel"), () => updateStatus(item.id, "cancelled"), "btn ghost")
                );
              }
              const calendarUrl = buildGoogleCalendarUrl(item);
              if (calendarUrl) {
                actions.appendChild(
                  createActionButton(t("action_google_calendar"), () => {
                    window.open(calendarUrl, "_blank", "noopener");
                  }, "btn ghost")
                );
              }
              if (item.preferred_date && item.preferred_time) {
                actions.appendChild(
                  createActionButton(t("action_samsung_calendar"), () => {
                    downloadSamsungIcs(item);
                  }, "btn ghost")
                );
              }
              card.appendChild(actions);
              requestsList.appendChild(card);
            });
          if (!filtered.length) {
            requestsList.innerHTML = `<p class="hint">${t("no_requests_found")}</p>`;
          }
        } catch (_error) {
          requestsStatus.textContent = t("failed_load_requests");
        }
      };

      saveAvailabilityBtn.addEventListener("click", saveAvailability);
      if (calendarToday) {
        calendarToday.addEventListener("click", () => {
          const todayKey = getDateKey(new Date());
          if (calendarView === "day" && calendarDay) {
            calendarDay.value = todayKey;
          } else if (calendarView === "month" && calendarMonth) {
            calendarMonth.value = todayKey.slice(0, 7);
          } else if (calendarStart) {
            calendarStart.value = todayKey;
          }
          renderCalendarView();
        });
      }
      if (calendarStart) {
        calendarStart.addEventListener("change", renderCalendarView);
      }
      if (calendarDay) {
        calendarDay.addEventListener("change", renderCalendarView);
      }
      if (calendarMonth) {
        calendarMonth.addEventListener("change", renderCalendarView);
      }
      calendarViewButtons.forEach((button) => {
        button.addEventListener("click", () => {
          setCalendarView(button.dataset.calendarView || "week");
        });
      });
      tourTzSelect.addEventListener("change", () => {
        const todayKey = getDateKey(new Date());
        if (calendarStart) {
          calendarStart.value = todayKey;
        }
        if (calendarDay) {
          calendarDay.value = todayKey;
        }
        if (calendarMonth) {
          calendarMonth.value = todayKey.slice(0, 7);
        }
        renderCalendarView();
      });
      addBlockedBtn.addEventListener("click", () => {
        blockedStatus.textContent = "";
        const date = blockedDate.value;
        const start = blockedStart.value;
        const end = blockedEnd.value;
        if (!date || !start || !end) {
          blockedStatus.textContent = "Add date, start time, and end time.";
          return;
        }
        const startMinutes = timeToMinutes(start);
        const endMinutes = timeToMinutes(end);
        if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
          blockedStatus.textContent = "End time must be after start time.";
          return;
        }
        for (let minutes = startMinutes; minutes < endMinutes; minutes += SLOT_MINUTES) {
          blockedSlots.push({
            date,
            start: minutesToTime(minutes),
            end: minutesToTime(Math.min(minutes + SLOT_MINUTES, endMinutes)),
            reason: blockedReason.value.trim(),
            kind: "manual",
          });
        }
        blockedDate.value = "";
        blockedStart.value = "";
        blockedEnd.value = "";
        blockedReason.value = "";
        renderBlockedSlots();
        renderCalendarView();
        queueAutoSave();
      });
      blockFullDayBtn.addEventListener("click", () => {
        blockedStatus.textContent = "";
        const date = blockedDate.value;
        if (!date) {
          blockedStatus.textContent = "Add a date first.";
          return;
        }
        const reason = blockedReason.value.trim();
        blockFullDayForDate(date, reason);
        blockedDate.value = "";
        blockedStart.value = "";
        blockedEnd.value = "";
        blockedReason.value = "";
        blockedStatus.textContent = "Full day blocked.";
        renderBlockedSlots();
        renderCalendarView();
        queueAutoSave();
      });
      blockFullRangeBtn.addEventListener("click", () => {
        blockedStatus.textContent = "";
        const startValue = blockedFullRangeStart.value;
        const endValue = blockedFullRangeEnd.value;
        if (!startValue || !endValue) {
          blockedStatus.textContent = "Add a start and end date.";
          return;
        }
        const startDate = parseDateKey(startValue);
        const endDate = parseDateKey(endValue);
        if (!startDate || !endDate || startDate > endDate) {
          blockedStatus.textContent = "End date must be after start date.";
          return;
        }
        const reason = blockedReason.value.trim();
        const current = new Date(startDate.getTime());
        while (current <= endDate) {
          blockFullDayForDate(toDateKey(current), reason);
          current.setUTCDate(current.getUTCDate() + 1);
        }
        blockedFullRangeStart.value = "";
        blockedFullRangeEnd.value = "";
        blockedReason.value = "";
        blockedStatus.textContent = "Full-day range blocked.";
        renderBlockedSlots();
        renderCalendarView();
        queueAutoSave();
      });
      addRecurringBtn.addEventListener("click", () => {
        if (!recurringStatus) return;
        recurringStatus.textContent = "";
        const days = getSelectedRecurringDays();
        if (!days.length) {
          recurringStatus.textContent = "Pick at least one day.";
          return;
        }
        const allDay = recurringAllDay && recurringAllDay.checked;
        const start = recurringStart ? recurringStart.value : "";
        const end = recurringEnd ? recurringEnd.value : "";
        if (!allDay) {
          const startMinutes = timeToMinutes(start);
          const endMinutes = timeToMinutes(end);
          if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
            recurringStatus.textContent = "End time must be after start time.";
            return;
          }
        }
        recurringBlocks.push({
          days,
          all_day: !!allDay,
          start: allDay ? "" : start,
          end: allDay ? "" : end,
          reason: recurringReason ? recurringReason.value.trim() : "",
        });
        if (recurringDays) {
          recurringDays.querySelectorAll("input[type=\"checkbox\"]:checked").forEach((input) => {
            input.checked = false;
          });
        }
        if (recurringAllDay) recurringAllDay.checked = false;
        if (recurringStart) recurringStart.value = "";
        if (recurringEnd) recurringEnd.value = "";
        if (recurringReason) recurringReason.value = "";
        recurringStatus.textContent = "Recurring block added.";
        renderRecurringList();
        renderCalendarView();
        queueAutoSave();
      });

      if (addTourRowBtn) {
        addTourRowBtn.addEventListener("click", () => {
          if (!tourScheduleList) return;
          tourScheduleList.appendChild(createTourRow());
        });
      }
      if (saveTourScheduleBtn) {
        saveTourScheduleBtn.addEventListener("click", saveTourSchedule);
      }
      if (clearCityTemplatesBtn) {
        clearCityTemplatesBtn.addEventListener("click", async () => {
          citySchedules = citySchedules.map((schedule) =>
            normalizeCitySchedule({
              ...schedule,
              ready_start: "00:00",
              leave_day_end: "23:59",
              has_sleep: false,
              sleep_days: [],
              has_break: false,
              break_days: [],
            })
          );
          renderCityScheduleWizard();
          applyCityTemplateBlocks({ announce: false });
          blockedSlots = normalizeBlockedSlots(blockedSlots.filter((slot) => slot && slot.kind !== "template"));
          renderBlockedSlots();
          renderCalendarView();
          queueAutoSave(t("template_rules_clearing"));
          await saveAvailability();
          if (cityScheduleStatus) {
            cityScheduleStatus.textContent = t("template_rules_cleared");
          }
        });
      }
      if (addGalleryRowBtn) {
        addGalleryRowBtn.addEventListener("click", () => {
          if (!galleryList) return;
          galleryList.appendChild(createGalleryRow());
        });
      }
      if (saveGalleryBtn) {
        saveGalleryBtn.addEventListener("click", saveGallery);
      }

      if (toggleBlockedListBtn) {
        setBlockedListVisible(false);
        toggleBlockedListBtn.addEventListener("click", () => {
          const isHidden = blockedList.classList.contains("hidden");
          setBlockedListVisible(isHidden);
        });
      }
      refreshBtn.addEventListener("click", loadRequests);
      statusFilter.addEventListener("change", loadRequests);
      window.setInterval(() => {
        if (document.visibilityState === "visible") {
          loadRequests();
        }
      }, 15000);

      const initialLanguage = getStoredLanguage() || detectBrowserLanguage();
      currentLanguage = initialLanguage;
      document.documentElement.setAttribute("lang", currentLanguage);

      setCalendarView("week");
      populateTimezones();
      loadAvailability();
      loadTourSchedule();
      loadGallery();
      loadRequests();
      applyLanguage(initialLanguage, true);
    