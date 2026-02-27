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
import android.webkit.HttpAuthHandler
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.TextView
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.core.app.NotificationManagerCompat
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
    private var filePathCallback: ValueCallback<Array<Uri>>? = null
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

        setupWebView()
        setupSwipeRefresh()
        val restoredState = savedInstanceState?.let { webView.restoreState(it) != null } ?: false
        if (!restoredState) {
            loadLastVisitedUrlOrDefault()
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
                handler?.proceed(ADMIN_USER, ADMIN_PASS)
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
                persistLastVisitedUrl(url)
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
        swipeRefresh.setOnRefreshListener {
            loadAdminUrl(forceFresh = true)
        }
        swipeRefresh.isEnabled = !webView.canScrollVertically(-1)
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
            val url = URL(TOKEN_ENDPOINT)
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
        val url = URL(REQUESTS_ENDPOINT)
        val connection = (url.openConnection() as HttpURLConnection).apply {
            requestMethod = "GET"
            connectTimeout = 10000
            readTimeout = 15000
            setRequestProperty("Accept", "application/json")
            setRequestProperty("X-Admin-Key", ADMIN_API_KEY)
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
        if (webView.url.isNullOrBlank()) {
            loadLastVisitedUrlOrDefault()
        }
        NotificationState.clearUnread(this)
        NotificationManagerCompat.from(this).cancel(PushMessagingService.BADGE_NOTIFICATION_ID)
        refreshUnreadBadge()
        syncUpcomingReminders()
        startPeriodicReminderSync()
    }

    override fun onPause() {
        persistLastVisitedUrl(webView.url)
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
        val baseUrl = currentUrl ?: ADMIN_URL
        val targetUrl = if (forceFresh) appendCacheBust(baseUrl) else baseUrl
        webView.loadUrl(targetUrl)
    }

    private fun loadLastVisitedUrlOrDefault() {
        val prefs = getSharedPreferences(APP_PREFS_NAME, MODE_PRIVATE)
        val savedUrl = prefs.getString(KEY_LAST_ADMIN_URL, null)
        if (!savedUrl.isNullOrBlank() && savedUrl.startsWith("http")) {
            webView.loadUrl(savedUrl)
        } else {
            loadAdminUrl(forceFresh = false)
        }
    }

    private fun persistLastVisitedUrl(url: String?) {
        if (url.isNullOrBlank() || !url.startsWith("http")) return
        getSharedPreferences(APP_PREFS_NAME, MODE_PRIVATE)
            .edit()
            .putString(KEY_LAST_ADMIN_URL, url)
            .apply()
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
        private const val ADMIN_URL = "https://heidivanhorny.com/booking/admin/"
        private const val TOKEN_ENDPOINT = "https://heidivanhorny.com/booking/api/admin/push-token.php"
        private const val REQUESTS_ENDPOINT = "https://heidivanhorny.com/booking/api/admin/requests.php"
        private const val ADMIN_API_KEY = "Simo.666$$$"
        private const val ADMIN_USER = "capitainecommando"
        private const val ADMIN_PASS = "Simo.666$$$"
        private const val REMINDER_SYNC_INTERVAL_MS = 60_000L
        private const val APP_PREFS_NAME = "booking_admin_app"
        private const val KEY_EXACT_ALARM_PROMPTED = "exact_alarm_prompted"
        private const val KEY_LAST_ADMIN_URL = "last_admin_url"
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
