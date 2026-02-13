package com.heidi.bookingadmin

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import java.net.HttpURLConnection
import java.net.URL
import java.util.Locale

class PushMessagingService : FirebaseMessagingService() {
    override fun onMessageReceived(message: RemoteMessage) {
        val title = message.notification?.title ?: "New booking"
        val body = message.notification?.body ?: "Open the admin panel to review."
        showNotification(title, body)
        if (message.data.isNotEmpty()) {
            storeClient(message.data)
        }
    }

    override fun onNewToken(token: String) {
        Thread {
            postToken(token)
        }.start()
    }

    private fun showNotification(title: String, body: String) {
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Booking alerts",
                NotificationManager.IMPORTANCE_HIGH
            )
            manager.createNotificationChannel(channel)
        }

        val intent = Intent(this, MainActivity::class.java)
        val pendingIntent = PendingIntent.getActivity(
            this,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(title)
            .setContentText(body)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .build()

        manager.notify(System.currentTimeMillis().toInt(), notification)
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
        if (email.isBlank()) return
        val name = data["name"]?.trim().orEmpty()
        val city = data["city"]?.trim().orEmpty()
        val phone = data["phone"]?.trim().orEmpty()
        val contactRaw = data["contact_ok"] ?: data["contact_followup"] ?: ""
        val normalized = contactRaw.lowercase(Locale.US)
        val contactOk = normalized == "yes" || normalized == "true"
        val client = ClientEntity(
            email = email,
            name = name,
            city = city,
            phone = phone,
            contactOk = contactOk,
            createdAt = System.currentTimeMillis()
        )
        Thread {
            ClientDatabase.getInstance(this).clientDao().upsert(client)
        }.start()
    }

    companion object {
        private const val CHANNEL_ID = "booking_alerts"
        private const val TOKEN_ENDPOINT = "https://heidivanhorny.com/booking/api/admin/push-token.php"
    }
}
