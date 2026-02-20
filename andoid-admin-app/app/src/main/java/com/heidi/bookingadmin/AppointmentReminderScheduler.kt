package com.heidi.bookingadmin

import android.app.AlarmManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import java.text.ParseException
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

object AppointmentReminderScheduler {
    private const val REMINDER_OFFSET_MS = 30 * 60 * 1000L

    fun scheduleFromBooking(
        context: Context,
        requestId: String,
        name: String,
        city: String,
        preferredDate: String,
        preferredTime: String
    ) {
        val id = resolveReminderId(requestId, name, preferredDate, preferredTime)
        val appointmentAt = parseLocalDateTime(preferredDate, preferredTime) ?: return
        val reminderAt = appointmentAt.time - REMINDER_OFFSET_MS
        val now = System.currentTimeMillis()
        if (reminderAt <= now) {
            return
        }

        val intent = Intent(context, AppointmentReminderReceiver::class.java).apply {
            putExtra("request_id", id)
            putExtra("client_name", name)
            putExtra("city", city)
            putExtra("preferred_date", preferredDate)
            putExtra("preferred_time", preferredTime)
        }

        val requestCode = id.hashCode()
        val pending = PendingIntent.getBroadcast(
            context,
            requestCode,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        try {
            alarmManager.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, reminderAt, pending)
        } catch (_: SecurityException) {
            alarmManager.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, reminderAt, pending)
        }
    }

    private fun resolveReminderId(requestId: String, name: String, date: String, time: String): String {
        val trimmed = requestId.trim()
        if (trimmed.isNotEmpty()) {
            return trimmed
        }
        return "${name.trim().lowercase(Locale.US)}|${date.trim()}|${time.trim()}"
    }

    private fun parseLocalDateTime(date: String, time: String): Date? {
        val normalizedDate = date.trim()
        val normalizedTime = time.trim()
        if (normalizedDate.isEmpty() || normalizedTime.isEmpty()) {
            return null
        }
        return try {
            val formatter = SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US)
            formatter.isLenient = false
            formatter.parse("$normalizedDate $normalizedTime")
        } catch (_: ParseException) {
            null
        }
    }
}

