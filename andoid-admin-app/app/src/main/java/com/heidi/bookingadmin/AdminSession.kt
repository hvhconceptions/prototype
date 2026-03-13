package com.heidi.bookingadmin

import android.content.Context

object AdminSession {
    const val PREFS_NAME = "booking_admin_app"

    const val ADMIN_URL = "https://heidivanhorny.com/booking/admin/"
    const val TOKEN_ENDPOINT = "https://heidivanhorny.com/booking/api/admin/push-token.php"
    const val REQUESTS_ENDPOINT = "https://heidivanhorny.com/booking/api/admin/requests.php"
    const val ADMIN_API_KEY = "HVH_2026_8f31c9d4a27b6e50f1c3"

    private const val KEY_ADMIN_USER = "admin_user"
    private const val KEY_ADMIN_PASS = "admin_pass"

    data class Credentials(
        val username: String,
        val password: String
    )

    fun getCredentials(context: Context): Credentials? {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val username = prefs.getString(KEY_ADMIN_USER, null)?.trim().orEmpty()
        val password = prefs.getString(KEY_ADMIN_PASS, null)?.trim().orEmpty()
        if (username.isBlank() || password.isBlank()) {
            return null
        }
        return Credentials(username = username, password = password)
    }

    fun saveCredentials(context: Context, username: String, password: String) {
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_ADMIN_USER, username.trim())
            .putString(KEY_ADMIN_PASS, password.trim())
            .apply()
    }

    fun clearCredentials(context: Context) {
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .remove(KEY_ADMIN_USER)
            .remove(KEY_ADMIN_PASS)
            .apply()
    }
}
