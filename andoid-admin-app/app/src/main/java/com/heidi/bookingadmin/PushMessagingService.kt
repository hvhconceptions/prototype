package com.heidi.bookingadmin

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import java.net.HttpURLConnection
import java.net.URL
import java.util.Locale

class PushMessagingService : FirebaseMessagingService() {
    override fun onMessageReceived(message: RemoteMessage) {
        if (message.data.isNotEmpty()) {
            storeClient(message.data)
        }

        val title = message.notification?.title ?: "New booking"
        val body = message.notification?.body ?: "Open the admin panel to review."
        val unreadCount = NotificationState.incrementUnread(this)
        val overviewLines = getOverviewLines()
        showNotification(title, body, unreadCount, overviewLines)
    }

    override fun onNewToken(token: String) {
        Thread {
            postToken(token)
        }.start()
    }

    private fun showNotification(title: String, body: String, unreadCount: Int, overviewLines: List<String>) {
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val soundUri = Uri.parse("android.resource://$packageName/${R.raw.booking_ping}")
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Booking alerts",
                NotificationManager.IMPORTANCE_HIGH
            )
            channel.description = "New booking requests and alerts"
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

        val intent = Intent(this, MainActivity::class.java)
        intent.putExtra(MainActivity.EXTRA_OPEN_NOTIFICATIONS, true)
        val pendingIntent = PendingIntent.getActivity(
            this,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val style = NotificationCompat.InboxStyle()
            .setSummaryText("$unreadCount new request(s)")
        for (line in overviewLines) {
            style.addLine(line)
        }

        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(style)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE)
            .setDefaults(NotificationCompat.DEFAULT_ALL)
            .setSound(soundUri)
            .setNumber(unreadCount)
            .setBadgeIconType(NotificationCompat.BADGE_ICON_SMALL)
            .build()

        NotificationManagerCompat.from(this).notify(BADGE_NOTIFICATION_ID, notification)
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

    private fun storeClient(data: Map<String, String>) {
        val email = data["email"]?.trim().orEmpty()
        val name = data["name"]?.trim().orEmpty()
        val city = data["city"]?.trim().orEmpty()
        val phone = data["phone"]?.trim().orEmpty()
        val requestId = data["id"]?.trim().orEmpty()
        val preferredDate = data["preferred_date"]?.trim().orEmpty()
        val preferredTime = data["preferred_time"]?.trim().orEmpty()
        val durationHours = data["duration_hours"]?.trim().orEmpty()
        val contactRaw = data["contact_ok"] ?: data["contact_followup"] ?: ""
        val normalized = contactRaw.lowercase(Locale.US)
        val contactOk = normalized == "yes" || normalized == "true"
        val entryId = when {
            requestId.isNotBlank() -> requestId
            email.isNotBlank() -> "push_${System.currentTimeMillis()}_${email.hashCode()}"
            else -> "push_${System.currentTimeMillis()}"
        }
        val client = ClientEntity(
            id = entryId,
            email = email,
            name = name,
            city = city,
            phone = phone,
            contactOk = contactOk,
            createdAt = System.currentTimeMillis()
        )
        ClientDatabase.getInstance(this).clientDao().upsert(client)

        if (preferredDate.isNotBlank() && preferredTime.isNotBlank()) {
            AppointmentReminderScheduler.scheduleFromBooking(
                context = this,
                requestId = entryId,
                name = name,
                city = city,
                preferredDate = preferredDate,
                preferredTime = preferredTime,
                durationHours = durationHours
            )
        }
    }

    private fun getOverviewLines(): List<String> {
        return try {
            val recent = ClientDatabase.getInstance(this).clientDao().getRecent(5)
            recent.map {
                val displayName = it.name.ifBlank { "Unknown" }
                "$displayName - ${it.city.ifBlank { "City not set" }}"
            }
        } catch (_: Exception) {
            emptyList()
        }
    }

    companion object {
        // Channel id bumped so Android picks up the new custom sound on existing installs.
        private const val CHANNEL_ID = "booking_alerts_v2"
        const val BADGE_NOTIFICATION_ID = 1101
        private const val TOKEN_ENDPOINT = "https://heidivanhorny.com/booking/api/admin/push-token.php"
    }
}
