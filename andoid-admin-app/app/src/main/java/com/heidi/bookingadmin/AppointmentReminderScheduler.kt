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
    fun scheduleFromBooking(
        context: Context,
        requestId: String,
        name: String,
        city: String,
        preferredDate: String,
        preferredTime: String,
        durationHours: String?
    ) {
        val id = resolveReminderId(requestId, name, preferredDate, preferredTime)
        val appointmentAt = parseLocalDateTime(preferredDate, preferredTime) ?: return
        val now = System.currentTimeMillis()
        if (appointmentAt.time > now) {
            scheduleAlarm(
                context = context,
                id = id,
                triggerAt = appointmentAt.time,
                name = name,
                city = city,
                preferredDate = preferredDate,
                preferredTime = preferredTime,
                eventType = "start"
            )
        }

        val durationMinutes = parseDurationMinutes(durationHours)
        if (durationMinutes <= 0) {
            return
        }
        val appointmentEnd = appointmentAt.time + (durationMinutes * 60_000L)
        if (appointmentEnd > now) {
            scheduleAlarm(
                context = context,
                id = id,
                triggerAt = appointmentEnd,
                name = name,
                city = city,
                preferredDate = preferredDate,
                preferredTime = preferredTime,
                eventType = "end"
            )
        }
    }

    private fun scheduleAlarm(
        context: Context,
        id: String,
        triggerAt: Long,
        name: String,
        city: String,
        preferredDate: String,
        preferredTime: String,
        eventType: String
    ) {
        val intent = Intent(context, AppointmentReminderReceiver::class.java).apply {
            putExtra("request_id", id)
            putExtra("client_name", name)
            putExtra("city", city)
            putExtra("preferred_date", preferredDate)
            putExtra("preferred_time", preferredTime)
            putExtra("event_type", eventType)
        }

        val requestCode = "$id|$eventType".hashCode()
        val pending = PendingIntent.getBroadcast(
            context,
            requestCode,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        try {
            alarmManager.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, triggerAt, pending)
        } catch (_: SecurityException) {
            alarmManager.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, triggerAt, pending)
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

    private fun parseDurationMinutes(durationHours: String?): Int {
        val hours = durationHours?.trim()?.toDoubleOrNull() ?: return 0
        if (hours <= 0.0 || !hours.isFinite()) {
            return 0
        }
        return (hours * 60.0).toInt()
    }
}
