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
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.TextView
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
    private lateinit var offlineMessage: TextView
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

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.adminWebView)
        offlineMessage = findViewById(R.id.offlineMessage)

        setupWebView()
        loadAdminUrl(forceFresh = true)
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
                    offlineMessage.visibility = View.VISIBLE
                    webView.visibility = View.GONE
                }
            }

            override fun onPageFinished(view: WebView, url: String) {
                offlineMessage.visibility = View.GONE
                webView.visibility = View.VISIBLE
            }
        }

        webView.setDownloadListener { url, _, _, _, _ ->
            if (!url.isNullOrBlank()) {
                openExternalLink(Uri.parse(url))
            }
        }
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
                val entries = fetchUpcomingBookings()
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

    private fun fetchUpcomingBookings(): List<ReminderBooking> {
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
                emptyList()
            } else {
                val body = connection.inputStream.use { stream ->
                    BufferedReader(InputStreamReader(stream)).use { it.readText() }
                }
                parseReminderBookings(body)
            }
        } finally {
            connection.disconnect()
        }
    }

    private fun parseReminderBookings(rawJson: String): List<ReminderBooking> {
        val json = JSONObject(rawJson)
        val requests = json.optJSONArray("requests") ?: JSONArray()
        val list = mutableListOf<ReminderBooking>()
        for (i in 0 until requests.length()) {
            val item = requests.optJSONObject(i) ?: continue
            val status = item.optString("status", "").trim().lowercase()
            val paymentStatus = item.optString("payment_status", "").trim().lowercase()
            val isConfirmed = status == "accepted" || status == "paid" || paymentStatus == "paid"
            if (!isConfirmed) continue

            val preferredDate = item.optString("preferred_date", "").trim()
            val preferredTime = item.optString("preferred_time", "").trim()
            if (preferredDate.isBlank() || preferredTime.isBlank()) continue

            val id = item.optString("id", "").trim()
            val name = item.optString("name", "").trim()
            val city = item.optString("city", "").trim()
            list.add(
                ReminderBooking(
                    id = id,
                    name = name,
                    city = city,
                    preferredDate = preferredDate,
                    preferredTime = preferredTime,
                    durationHours = item.optString("duration_hours", "").trim(),
                    durationLabel = item.optString("duration_label", "").trim()
                )
            )
        }
        return list
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
        loadAdminUrl(forceFresh = true)
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

    private fun loadAdminUrl(forceFresh: Boolean = false) {
        val url = if (forceFresh) {
            "$ADMIN_URL?v=${System.currentTimeMillis()}"
        } else {
            ADMIN_URL
        }
        webView.loadUrl(url)
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
}
