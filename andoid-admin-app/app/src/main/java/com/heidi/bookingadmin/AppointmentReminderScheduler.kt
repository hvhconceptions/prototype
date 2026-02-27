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
    private val reminderEventTypes = listOf("prestart", "start", "end")

    fun scheduleFromBooking(
        context: Context,
        requestId: String,
        name: String,
        city: String,
        preferredDate: String,
        preferredTime: String,
        durationHours: String?,
        durationLabel: String? = null
    ) {
        val id = resolveReminderId(requestId, name, preferredDate, preferredTime)
        // Always reset previous alarms for this booking id before scheduling fresh ones.
        cancelForRequest(context, id)

        val appointmentAt = parseLocalDateTime(preferredDate, preferredTime) ?: return
        val now = System.currentTimeMillis()

        val prestartAt = appointmentAt.time - PRESTART_LEAD_MS
        if (prestartAt > now) {
            scheduleAlarm(
                context = context,
                id = id,
                triggerAt = prestartAt,
                name = name,
                city = city,
                preferredDate = preferredDate,
                preferredTime = preferredTime,
                eventType = "prestart"
            )
        }

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

        val durationMinutes = parseDurationMinutes(durationHours, durationLabel)
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

    fun cancelForRequest(context: Context, requestId: String, name: String = "", preferredDate: String = "", preferredTime: String = "") {
        val id = resolveReminderId(requestId, name, preferredDate, preferredTime)
        if (id.isBlank()) return
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        for (eventType in reminderEventTypes) {
            val intent = Intent(context, AppointmentReminderReceiver::class.java).apply {
                putExtra("request_id", id)
                putExtra("event_type", eventType)
            }
            val requestCode = "$id|$eventType".hashCode()
            val pending = PendingIntent.getBroadcast(
                context,
                requestCode,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            alarmManager.cancel(pending)
            pending.cancel()
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
        val normalizedTime = time.trim().replace('.', ':').uppercase(Locale.US)
        if (normalizedDate.isEmpty() || normalizedTime.isEmpty()) {
            return null
        }
        val candidates = listOf(
            "yyyy-MM-dd HH:mm",
            "yyyy-MM-dd H:mm",
            "yyyy-MM-dd hh:mm a",
            "yyyy-MM-dd h:mm a"
        )
        for (pattern in candidates) {
            try {
                val formatter = SimpleDateFormat(pattern, Locale.US)
                formatter.isLenient = false
                val parsed = formatter.parse("$normalizedDate $normalizedTime")
                if (parsed != null) {
                    return parsed
                }
            } catch (_: ParseException) {
            }
        }
        return null
    }

    private fun parseDurationMinutes(durationHours: String?, durationLabel: String?): Int {
        val normalizedHours = durationHours?.trim()?.replace(',', '.')
        val fromHours = normalizedHours?.toDoubleOrNull()
        if (fromHours != null && fromHours > 0.0 && fromHours.isFinite()) {
            return (fromHours * 60.0).toInt().coerceAtLeast(1)
        }

        val label = durationLabel?.trim()?.lowercase(Locale.US).orEmpty()
        if (label.isEmpty()) {
            return 0
        }

        val hourRegex = Regex("""(\d+(?:[.,]\d+)?)\s*h""")
        val minuteRegex = Regex("""(\d+(?:[.,]\d+)?)\s*m""")
        val hourMatch = hourRegex.find(label)?.groupValues?.getOrNull(1)?.replace(',', '.')?.toDoubleOrNull()
        val minuteMatch = minuteRegex.find(label)?.groupValues?.getOrNull(1)?.replace(',', '.')?.toDoubleOrNull()
        val totalMinutes = when {
            hourMatch != null && hourMatch > 0.0 -> (hourMatch * 60.0).toInt()
            minuteMatch != null && minuteMatch > 0.0 -> minuteMatch.toInt()
            else -> 0
        }
        return totalMinutes.coerceAtLeast(0)
    }

    private const val PRESTART_LEAD_MS = 30L * 60L * 1000L
}
