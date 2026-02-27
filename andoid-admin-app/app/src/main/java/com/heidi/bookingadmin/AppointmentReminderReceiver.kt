package com.heidi.bookingadmin

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.Locale

class AppointmentReminderReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val result = goAsync()
        Thread {
            try {
                val clientName = intent.getStringExtra("client_name").orEmpty().ifBlank { "Client" }
                val city = intent.getStringExtra("city").orEmpty()
                val date = intent.getStringExtra("preferred_date").orEmpty()
                val time = intent.getStringExtra("preferred_time").orEmpty()
                val eventType = intent.getStringExtra("event_type").orEmpty().trim().lowercase()
                val requestId = intent.getStringExtra("request_id").orEmpty().ifBlank { "$clientName|$date|$time" }

                if (eventType !in setOf("prestart", "start", "end")) {
                    return@Thread
                }

                val stillActive = isRequestStillReminderEligible(
                    requestId = requestId,
                    clientName = clientName,
                    preferredDate = date,
                    preferredTime = time
                )
                if (!stillActive) {
                    AppointmentReminderScheduler.cancelForRequest(
                        context = context,
                        requestId = requestId,
                        name = clientName,
                        preferredDate = date,
                        preferredTime = time
                    )
                    return@Thread
                }

                val soundUri = Uri.parse("android.resource://${context.packageName}/${R.raw.its_britney}")
                val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    val channel = NotificationChannel(
                        CHANNEL_ID,
                        "Appointment reminders",
                        NotificationManager.IMPORTANCE_HIGH
                    )
                    channel.description = "Appointment 30-minute/start/end reminders"
                    channel.enableVibration(true)
                    channel.setShowBadge(true)
                    channel.setSound(
                        soundUri,
                        AudioAttributes.Builder()
                            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                            .build()
                    )
                    manager.createNotificationChannel(channel)
                }

                val openIntent = Intent(context, MainActivity::class.java).apply {
                    putExtra(MainActivity.EXTRA_OPEN_NOTIFICATIONS, true)
                }
                val contentPendingIntent = PendingIntent.getActivity(
                    context,
                    requestId.hashCode(),
                    openIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )

                val title = when (eventType) {
                    "prestart" -> "It's Britney bitch - Appointment in 30 minutes"
                    "end" -> "It's Britney bitch - Appointment ended"
                    else -> "It's Britney bitch - Appointment starting now"
                }
                val details = buildString {
                    append(clientName)
                    if (city.isNotBlank()) {
                        append(" - ")
                        append(city)
                    }
                    if (date.isNotBlank() || time.isNotBlank()) {
                        append(" (")
                        append(listOf(date, time).filter { it.isNotBlank() }.joinToString(" "))
                        append(")")
                    }
                }

                val notification = NotificationCompat.Builder(context, CHANNEL_ID)
                    .setSmallIcon(android.R.drawable.ic_lock_idle_alarm)
                    .setContentTitle(title)
                    .setContentText(details)
                    .setPriority(NotificationCompat.PRIORITY_HIGH)
                    .setCategory(NotificationCompat.CATEGORY_REMINDER)
                    .setAutoCancel(true)
                    .setContentIntent(contentPendingIntent)
                    .setSound(soundUri)
                    .build()

                val eventKey = "$requestId|$eventType"
                NotificationManagerCompat.from(context).notify(BASE_NOTIFICATION_ID + eventKey.hashCode(), notification)
            } finally {
                result.finish()
            }
        }.start()
    }

    private fun isRequestStillReminderEligible(
        requestId: String,
        clientName: String,
        preferredDate: String,
        preferredTime: String
    ): Boolean {
        if (requestId.isBlank()) return false
        return try {
            val url = URL(REQUESTS_ENDPOINT)
            val connection = (url.openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                connectTimeout = 7000
                readTimeout = 9000
                setRequestProperty("Accept", "application/json")
                setRequestProperty("X-Admin-Key", ADMIN_API_KEY)
            }
            try {
                val code = connection.responseCode
                if (code !in 200..299) {
                    false
                } else {
                    val body = connection.inputStream.bufferedReader().use { it.readText() }
                    val json = JSONObject(body)
                    val requests = json.optJSONArray("requests") ?: return false
                    for (i in 0 until requests.length()) {
                        val item = requests.optJSONObject(i) ?: continue
                        val itemId = item.optString("id", "").trim()
                        val idMatches = itemId.isNotBlank() && itemId == requestId
                        val fallbackMatches = itemId.isBlank() &&
                            item.optString("name", "").trim().equals(clientName, ignoreCase = true) &&
                            item.optString("preferred_date", "").trim() == preferredDate &&
                            item.optString("preferred_time", "").trim() == preferredTime
                        if (!idMatches && !fallbackMatches) {
                            continue
                        }
                        val status = item.optString("status", "").trim().lowercase(Locale.US)
                        val paymentStatus = item.optString("payment_status", "").trim().lowercase(Locale.US)
                        val removed = status == "declined" || status == "cancelled" || status == "blacklisted"
                        if (removed) return false
                        val confirmed = status == "accepted" || status == "paid" || paymentStatus == "paid"
                        return confirmed
                    }
                    false
                }
            } finally {
                connection.disconnect()
            }
        } catch (_: Exception) {
            false
        }
    }

    companion object {
        private const val CHANNEL_ID = "appointment_reminders_v4"
        private const val BASE_NOTIFICATION_ID = 410000
        private const val REQUESTS_ENDPOINT = "https://heidivanhorny.com/booking/api/admin/requests.php"
        private const val ADMIN_API_KEY = "Simo.666$$$"
    }
}
