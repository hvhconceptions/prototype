package com.heidi.bookingadmin

import android.content.Context

object NotificationState {
    private const val PREFS_NAME = "booking_notifications"
    private const val KEY_UNREAD_COUNT = "unread_count"

    fun incrementUnread(context: Context): Int {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val next = prefs.getInt(KEY_UNREAD_COUNT, 0) + 1
        prefs.edit().putInt(KEY_UNREAD_COUNT, next).apply()
        return next
    }

    fun getUnread(context: Context): Int {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        return prefs.getInt(KEY_UNREAD_COUNT, 0)
    }

    fun setUnread(context: Context, count: Int) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        prefs.edit().putInt(KEY_UNREAD_COUNT, count.coerceAtLeast(0)).apply()
    }

    fun clearUnread(context: Context) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        prefs.edit().putInt(KEY_UNREAD_COUNT, 0).apply()
    }
}
