package com.heidi.bookingadmin

import android.Manifest
import android.app.AlarmManager
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import android.view.View
import android.webkit.CookieManager
import android.webkit.HttpAuthHandler
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewDatabase
import android.webkit.WebViewClient
import android.widget.TextView
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.appcompat.widget.PopupMenu
import androidx.core.content.ContextCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.res.ResourcesCompat
import com.google.firebase.messaging.FirebaseMessaging
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.atomic.AtomicBoolean

class MainActivity : AppCompatActivity() {
    private lateinit var webView: WebView
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private lateinit var offlineMessage: TextView
    private lateinit var menuButton: TextView
    private var filePathCallback: ValueCallback<Array<Uri>>? = null
    private var authAttemptsInWindow = 0
    private var authWindowStartedAt = 0L
    private var loginRedirectInProgress = false
    private val syncHandler = Handler(Looper.getMainLooper())
    private val reminderSyncInProgress = AtomicBoolean(false)
    private val periodicReminderSync = object : Runnable {
        override fun run() {
            syncUpcomingReminders()
            syncHandler.postDelayed(this, REMINDER_SYNC_INTERVAL_MS)
        }
    }

    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { _ -> }

    private val fileChooserLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        val callback = filePathCallback
        filePathCallback = null
        if (callback == null) return@registerForActivityResult
        if (result.resultCode != RESULT_OK) {
            callback.onReceiveValue(null)
            return@registerForActivityResult
        }
        val data = result.data
        val clipData = data?.clipData
        if (clipData != null && clipData.itemCount > 0) {
            val uris = Array(clipData.itemCount) { index -> clipData.getItemAt(index).uri }
            callback.onReceiveValue(uris)
            return@registerForActivityResult
        }
        val uri = data?.data
        if (uri != null) {
            callback.onReceiveValue(arrayOf(uri))
        } else {
            callback.onReceiveValue(null)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        swipeRefresh = findViewById(R.id.swipeRefresh)
        webView = findViewById(R.id.adminWebView)
        offlineMessage = findViewById(R.id.offlineMessage)
        menuButton = findViewById(R.id.menuButton)

        setupHeaderMenu()
        if (!hasSavedLogin()) {
            openLoginScreen()
            return
        }

        setupWebView()
        setupSwipeRefresh()
        val restoredState = savedInstanceState?.let { webView.restoreState(it) != null } ?: false
        if (!restoredState) {
            loadAdminUrl(forceFresh = false)
        }
        refreshUnreadBadge()

        requestNotificationPermissionIfNeeded()
        requestExactAlarmPermissionIfNeeded()
        registerFcmToken()
        syncUpcomingReminders()

        if (intent?.getBooleanExtra(EXTRA_OPEN_NOTIFICATIONS, false) == true) {
            openNotificationsFeed()
        }
    }

    private fun setupWebView() {
        val settings = webView.settings
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
        settings.useWideViewPort = true
        settings.loadWithOverviewMode = true
        settings.cacheMode = WebSettings.LOAD_NO_CACHE
        settings.userAgentString = settings.userAgentString + " HVHApp/1.0"

        webView.clearCache(true)
        webView.webChromeClient = object : WebChromeClient() {
            override fun onShowFileChooser(
                view: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                if (filePathCallback == null) return false
                this@MainActivity.filePathCallback?.onReceiveValue(null)
                this@MainActivity.filePathCallback = filePathCallback
                return try {
                    val chooserIntent = fileChooserParams?.createIntent() ?: Intent(Intent.ACTION_GET_CONTENT).apply {
                        addCategory(Intent.CATEGORY_OPENABLE)
                        type = "image/*"
                    }
                    fileChooserLauncher.launch(chooserIntent)
                    true
                } catch (_: Exception) {
                    this@MainActivity.filePathCallback = null
                    false
                }
            }
        }

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val uri = request.url ?: return false
                val target = uri.toString()
                if (shouldOpenExternally(target)) {
                    openExternalLink(uri)
                    return true
                }
                return false
            }

            override fun onReceivedHttpAuthRequest(
                view: WebView?,
                handler: HttpAuthHandler?,
                host: String?,
                realm: String?
            ) {
                val credentials = AdminSession.getCredentials(this@MainActivity)
                if (credentials == null) {
                    handler?.cancel()
                    runOnUiThread {
                        openLoginScreen(getString(R.string.login_required_message))
                    }
                    return
                }

                val now = System.currentTimeMillis()
                if (now - authWindowStartedAt > AUTH_ATTEMPT_WINDOW_MS) {
                    authWindowStartedAt = now
                    authAttemptsInWindow = 0
                }
                authAttemptsInWindow += 1
                if (authAttemptsInWindow > MAX_AUTH_ATTEMPTS_IN_WINDOW) {
                    handler?.cancel()
                    runOnUiThread {
                        clearSessionState()
                        openLoginScreen(getString(R.string.login_error_too_many_attempts))
                    }
                    return
                }
                handler?.proceed(credentials.username, credentials.password)
            }

            override fun onReceivedError(
                view: WebView,
                request: WebResourceRequest,
                error: WebResourceError
            ) {
                if (request.isForMainFrame) {
                    swipeRefresh.isRefreshing = false
                    offlineMessage.visibility = View.VISIBLE
                    webView.visibility = View.GONE
                }
            }

            override fun onPageFinished(view: WebView, url: String) {
                swipeRefresh.isRefreshing = false
                offlineMessage.visibility = View.GONE
                webView.visibility = View.VISIBLE
                swipeRefresh.isEnabled = !webView.canScrollVertically(-1)
                authAttemptsInWindow = 0
                authWindowStartedAt = 0L
                injectSoftModeStyles()
            }
        }

        webView.setDownloadListener { url, _, _, _, _ ->
            if (!url.isNullOrBlank()) {
                openExternalLink(Uri.parse(url))
            }
        }

        webView.setOnScrollChangeListener { _, _, _, _, _ ->
            swipeRefresh.isEnabled = !webView.canScrollVertically(-1)
        }
    }

    private fun setupSwipeRefresh() {
        swipeRefresh.setProgressBackgroundColorSchemeColor(
            ResourcesCompat.getColor(resources, R.color.surface_card, theme)
        )
        swipeRefresh.setColorSchemeColors(
            ResourcesCompat.getColor(resources, R.color.brand_primary, theme),
            ResourcesCompat.getColor(resources, R.color.brand_secondary, theme)
        )
        swipeRefresh.setOnRefreshListener {
            loadAdminUrl(forceFresh = true)
        }
        swipeRefresh.isEnabled = !webView.canScrollVertically(-1)
    }

    private fun setupHeaderMenu() {
        menuButton.setOnClickListener { anchor ->
            PopupMenu(this, anchor).apply {
                menu.add(0, MENU_CLIENTS, 0, getString(R.string.menu_clients))
                menu.add(0, MENU_LOGOUT, 1, getString(R.string.menu_logout))
                setOnMenuItemClickListener { item ->
                    when (item.itemId) {
                        MENU_CLIENTS -> {
                            openNotificationsFeed()
                            true
                        }
                        MENU_LOGOUT -> {
                            clearSessionState()
                            openLoginScreen(getString(R.string.logout_message))
                            true
                        }
                        else -> false
                    }
                }
            }.show()
        }
    }

    private fun hasSavedLogin(): Boolean {
        return AdminSession.getCredentials(this) != null
    }

    private fun clearSessionState() {
        AdminSession.clearCredentials(this)
        authAttemptsInWindow = 0
        authWindowStartedAt = 0L
        webView.stopLoading()
        webView.clearHistory()
        webView.clearCache(true)
        WebViewDatabase.getInstance(this).clearHttpAuthUsernamePassword()
        CookieManager.getInstance().removeAllCookies(null)
        CookieManager.getInstance().flush()
    }

    private fun openLoginScreen(message: String? = null) {
        if (loginRedirectInProgress) return
        loginRedirectInProgress = true
        startActivity(
            Intent(this, LoginActivity::class.java).apply {
                if (!message.isNullOrBlank()) {
                    putExtra(LoginActivity.EXTRA_MESSAGE, message)
                }
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            }
        )
        finish()
    }

    private fun shouldOpenExternally(url: String): Boolean {
        val lower = url.lowercase()
        return lower.contains("google.com/calendar/render") ||
            lower.contains("calendar.google.com/calendar") ||
            lower.contains("outlook.live.com/calendar") ||
            lower.contains("/booking/api/calendar.php") ||
            lower.endsWith(".ics")
    }

    private fun openExternalLink(uri: Uri) {
        try {
            startActivity(Intent(Intent.ACTION_VIEW, uri))
        } catch (_: Exception) {
        }
    }

    private fun injectSoftModeStyles() {
        val css = """
            (function () {
              var styleId = 'hvh-app-kawaii-style-v3';
              var badgeId = 'hvh-kawaii-badge';
              var intervalId = 'hvh-kawaii-refresh';

              function ensureStyle() {
                var style = document.getElementById(styleId);
                if (!style) {
                  style = document.createElement('style');
                  style.id = styleId;
                  style.textContent = `
                    html, body {
                      background: linear-gradient(160deg, #fffdfd 0%, #fff6fb 52%, #f3f9ff 100%) !important;
                      color: #3a2c4a !important;
                      font-family: "Quicksand", "M PLUS Rounded 1c", "Hiragino Kaku Gothic ProN", "Yu Gothic", "Meiryo", "Segoe UI", sans-serif !important;
                      letter-spacing: 0.01em !important;
                    }
                    body::before, body::after,
                    [class*="matrix"], [id*="matrix"], .matrix-rain, .matrix-layer, .matrix-bg, .rain-layer,
                    canvas.matrix, canvas#matrixRain, canvas#matrixCanvas {
                      display: none !important;
                      opacity: 0 !important;
                      visibility: hidden !important;
                      animation: none !important;
                    }
                    .calendar-shell, .schedule-shell, .panel, .card, .request-card, .status-panel, .section-panel,
                    .menu-group, .admin-shell, .clients-shell, .schedule-grid-wrap, .requests-wrap, .calendar-wrap {
                      background: rgba(255, 255, 255, 0.95) !important;
                      border: 1px solid #ead8ec !important;
                      box-shadow: 0 10px 22px rgba(198, 161, 199, 0.18) !important;
                      backdrop-filter: blur(4px) !important;
                    }
                    h1, h2, h3, .title, .section-title {
                      color: #9c5eb0 !important;
                      text-transform: none !important;
                      letter-spacing: 0.02em !important;
                    }
                    button, .button, .status-action, .status-filter-tab, .menu-link, .action-btn {
                      border-radius: 18px !important;
                      text-transform: none !important;
                      letter-spacing: 0.02em !important;
                      border: 1px solid #e7cde7 !important;
                      background: linear-gradient(135deg, #ffe8f4 0%, #f8ebff 100%) !important;
                      color: #5a3c6f !important;
                      box-shadow: 0 6px 14px rgba(212, 173, 213, 0.2) !important;
                    }
                    input, select, textarea {
                      border-radius: 14px !important;
                      border: 1px solid #e8d5ec !important;
                      background: #fffbfd !important;
                      color: #3f2d4f !important;
                      box-shadow: none !important;
                    }
                    .calendar-slot, .calendar-cell {
                      border-color: #ecdbee !important;
                      background: #fffafe !important;
                    }
                    .calendar-slot.maybe {
                      box-shadow: inset 0 0 0 1px rgba(214, 133, 184, 0.55) !important;
                      background: #fff4fa !important;
                    }
                    .slot-maybe-label { color: #9c5eb0 !important; font-weight: 700 !important; }
                    a { color: #a269bf !important; }
                  `;
                  document.head.appendChild(style);
                }
              }

              function ensureBadge() {
                if (document.getElementById(badgeId)) return;
                var badge = document.createElement('div');
                badge.id = badgeId;
                badge.textContent = 'Kawaii mode ON';
                badge.style.cssText = 'position:fixed;right:10px;bottom:10px;z-index:2147483647;padding:6px 10px;border-radius:999px;background:#f8e8ff;color:#744a92;font-size:11px;font-weight:700;border:1px solid #e5c8ef;box-shadow:0 6px 14px rgba(160,120,190,.25);pointer-events:none;';
                document.body.appendChild(badge);
              }

              function hideMatrixCanvases() {
                var nodes = document.querySelectorAll('canvas');
                nodes.forEach(function (node) {
                  var id = (node.id || '').toLowerCase();
                  var cls = (node.className || '').toString().toLowerCase();
                  if (id.indexOf('matrix') >= 0 || cls.indexOf('matrix') >= 0 || cls.indexOf('rain') >= 0) {
                    node.style.display = 'none';
                  }
                });
              }

              ensureStyle();
              ensureBadge();
              hideMatrixCanvases();

              if (!window[intervalId]) {
                window[intervalId] = window.setInterval(function () {
                  ensureStyle();
                  ensureBadge();
                  hideMatrixCanvases();
                }, 1500);
              }
            })();
        """.trimIndent()
        webView.evaluateJavascript(css, null)
    }

    private fun requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) return
        val permission = Manifest.permission.POST_NOTIFICATIONS
        val granted = ContextCompat.checkSelfPermission(this, permission) == PackageManager.PERMISSION_GRANTED
        if (!granted) {
            requestPermissionLauncher.launch(permission)
        }
    }

    private fun requestExactAlarmPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) return
        val alarmManager = getSystemService(ALARM_SERVICE) as AlarmManager
        if (alarmManager.canScheduleExactAlarms()) return

        val prefs = getSharedPreferences(APP_PREFS_NAME, MODE_PRIVATE)
        if (prefs.getBoolean(KEY_EXACT_ALARM_PROMPTED, false)) return
        prefs.edit().putBoolean(KEY_EXACT_ALARM_PROMPTED, true).apply()

        val intent = Intent(Settings.ACTION_REQUEST_SCHEDULE_EXACT_ALARM).apply {
            data = Uri.parse("package:$packageName")
        }
        try {
            startActivity(intent)
        } catch (_: Exception) {
        }
    }

    private fun registerFcmToken() {
        FirebaseMessaging.getInstance().token
            .addOnSuccessListener { token ->
                Thread {
                    postToken(token)
                }.start()
            }
    }

    private fun postToken(token: String) {
        try {
            val url = URL(AdminSession.TOKEN_ENDPOINT)
            val connection = url.openConnection() as HttpURLConnection
            connection.requestMethod = "POST"
            connection.setRequestProperty("Content-Type", "application/json")
            connection.doOutput = true
            val payload = "{\"token\":\"$token\",\"platform\":\"android\"}"
            connection.outputStream.use { it.write(payload.toByteArray()) }
            connection.responseCode
            connection.disconnect()
        } catch (_: Exception) {
        }
    }

    private fun syncUpcomingReminders() {
        if (!reminderSyncInProgress.compareAndSet(false, true)) {
            return
        }
        Thread {
            try {
                val snapshot = fetchReminderSyncSnapshot()
                for (requestId in snapshot.cancelIds) {
                    AppointmentReminderScheduler.cancelForRequest(
                        context = this,
                        requestId = requestId
                    )
                }

                val entries = snapshot.confirmedEntries
                for (entry in entries) {
                    AppointmentReminderScheduler.scheduleFromBooking(
                        context = this,
                        requestId = entry.id,
                        name = entry.name,
                        city = entry.city,
                        preferredDate = entry.preferredDate,
                        preferredTime = entry.preferredTime,
                        durationHours = entry.durationHours,
                        durationLabel = entry.durationLabel
                    )
                }
            } catch (_: Exception) {
            } finally {
                reminderSyncInProgress.set(false)
            }
        }.start()
    }

    private fun fetchReminderSyncSnapshot(): ReminderSyncSnapshot {
        val url = URL(AdminSession.REQUESTS_ENDPOINT)
        val connection = (url.openConnection() as HttpURLConnection).apply {
            requestMethod = "GET"
            connectTimeout = 10000
            readTimeout = 15000
            setRequestProperty("Accept", "application/json")
            setRequestProperty("X-Admin-Key", AdminSession.ADMIN_API_KEY)
        }
        return try {
            val code = connection.responseCode
            if (code !in 200..299) {
                ReminderSyncSnapshot(emptyList(), emptySet())
            } else {
                val body = connection.inputStream.use { stream ->
                    BufferedReader(InputStreamReader(stream)).use { it.readText() }
                }
                parseReminderSnapshot(body)
            }
        } finally {
            connection.disconnect()
        }
    }

    private fun parseReminderSnapshot(rawJson: String): ReminderSyncSnapshot {
        val json = JSONObject(rawJson)
        val requests = json.optJSONArray("requests") ?: JSONArray()
        val list = mutableListOf<ReminderBooking>()
        val cancelIds = linkedSetOf<String>()
        for (i in 0 until requests.length()) {
            val item = requests.optJSONObject(i) ?: continue
            val requestId = item.optString("id", "").trim()
            val status = item.optString("status", "").trim().lowercase()
            val paymentStatus = item.optString("payment_status", "").trim().lowercase()
            if (status == "declined" || status == "cancelled" || status == "blacklisted") {
                if (requestId.isNotBlank()) cancelIds.add(requestId)
                continue
            }

            val isConfirmed = status == "accepted" || status == "paid" || paymentStatus == "paid"
            if (!isConfirmed) {
                if (requestId.isNotBlank()) cancelIds.add(requestId)
                continue
            }

            val preferredDate = item.optString("preferred_date", "").trim()
            val preferredTime = item.optString("preferred_time", "").trim()
            if (preferredDate.isBlank() || preferredTime.isBlank()) {
                if (requestId.isNotBlank()) cancelIds.add(requestId)
                continue
            }

            val name = item.optString("name", "").trim()
            val city = item.optString("city", "").trim()
            list.add(
                ReminderBooking(
                    id = requestId,
                    name = name,
                    city = city,
                    preferredDate = preferredDate,
                    preferredTime = preferredTime,
                    durationHours = item.optString("duration_hours", "").trim(),
                    durationLabel = item.optString("duration_label", "").trim()
                )
            )
        }
        return ReminderSyncSnapshot(list, cancelIds)
    }

    override fun onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack()
        } else {
            super.onBackPressed()
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        if (intent.getBooleanExtra(EXTRA_OPEN_NOTIFICATIONS, false)) {
            openNotificationsFeed()
        }
    }

    override fun onResume() {
        super.onResume()
        loginRedirectInProgress = false
        if (webView.url.isNullOrBlank()) {
            loadAdminUrl(forceFresh = false)
        }
        NotificationState.clearUnread(this)
        NotificationManagerCompat.from(this).cancel(PushMessagingService.BADGE_NOTIFICATION_ID)
        refreshUnreadBadge()
        syncUpcomingReminders()
        startPeriodicReminderSync()
    }

    override fun onPause() {
        super.onPause()
        stopPeriodicReminderSync()
    }

    override fun onSaveInstanceState(outState: Bundle) {
        webView.saveState(outState)
        super.onSaveInstanceState(outState)
    }

    override fun onDestroy() {
        filePathCallback?.onReceiveValue(null)
        filePathCallback = null
        super.onDestroy()
    }

    private fun loadAdminUrl(forceFresh: Boolean = false) {
        val currentUrl = webView.url?.takeIf { it.startsWith("http") }
        val baseUrl = currentUrl ?: AdminSession.ADMIN_URL
        val targetUrl = if (forceFresh) appendCacheBust(baseUrl) else baseUrl
        webView.loadUrl(targetUrl)
    }

    private fun appendCacheBust(url: String): String {
        val uri = Uri.parse(url)
        val builder = uri.buildUpon().clearQuery()
        for (name in uri.queryParameterNames) {
            if (name == "v") continue
            for (value in uri.getQueryParameters(name)) {
                builder.appendQueryParameter(name, value)
            }
        }
        builder.appendQueryParameter("v", System.currentTimeMillis().toString())
        return builder.build().toString()
    }

    private fun refreshUnreadBadge() {
        val unread = NotificationState.getUnread(this)
        if (unread <= 0) {
            NotificationManagerCompat.from(this).cancel(PushMessagingService.BADGE_NOTIFICATION_ID)
        }
    }

    private fun openNotificationsFeed() {
        NotificationState.clearUnread(this)
        NotificationManagerCompat.from(this).cancelAll()
        refreshUnreadBadge()
        startActivity(Intent(this, ClientListActivity::class.java))
    }

    private fun startPeriodicReminderSync() {
        syncHandler.removeCallbacks(periodicReminderSync)
        syncHandler.postDelayed(periodicReminderSync, REMINDER_SYNC_INTERVAL_MS)
    }

    private fun stopPeriodicReminderSync() {
        syncHandler.removeCallbacks(periodicReminderSync)
    }

    companion object {
        const val EXTRA_OPEN_NOTIFICATIONS = "open_notifications"
        private const val REMINDER_SYNC_INTERVAL_MS = 60_000L
        private const val APP_PREFS_NAME = AdminSession.PREFS_NAME
        private const val KEY_EXACT_ALARM_PROMPTED = "exact_alarm_prompted"
        private const val AUTH_ATTEMPT_WINDOW_MS = 20_000L
        private const val MAX_AUTH_ATTEMPTS_IN_WINDOW = 2
        private const val MENU_CLIENTS = 1001
        private const val MENU_LOGOUT = 1002
    }

    private data class ReminderBooking(
        val id: String,
        val name: String,
        val city: String,
        val preferredDate: String,
        val preferredTime: String,
        val durationHours: String,
        val durationLabel: String
    )

    private data class ReminderSyncSnapshot(
        val confirmedEntries: List<ReminderBooking>,
        val cancelIds: Set<String>
    )
}
