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

class AppointmentReminderReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val clientName = intent.getStringExtra("client_name").orEmpty().ifBlank { "Client" }
        val city = intent.getStringExtra("city").orEmpty()
        val date = intent.getStringExtra("preferred_date").orEmpty()
        val time = intent.getStringExtra("preferred_time").orEmpty()
        val requestId = intent.getStringExtra("request_id").orEmpty().ifBlank { "$clientName|$date|$time" }

        val soundUri = Uri.parse("android.resource://${context.packageName}/${R.raw.its_britney}")
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Appointment reminders",
                NotificationManager.IMPORTANCE_HIGH
            )
            channel.description = "30-minute booking reminders"
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

        val title = "Client in 30 minutes"
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

        NotificationManagerCompat.from(context).notify(BASE_NOTIFICATION_ID + requestId.hashCode(), notification)
    }

    companion object {
        private const val CHANNEL_ID = "appointment_reminders_v1"
        private const val BASE_NOTIFICATION_ID = 410000
    }
}

