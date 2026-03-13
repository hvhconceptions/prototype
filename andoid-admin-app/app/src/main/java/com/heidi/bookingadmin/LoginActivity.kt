package com.heidi.bookingadmin

import android.content.Intent
import android.os.Bundle
import android.util.Base64
import android.view.View
import android.widget.Button
import android.widget.ProgressBar
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.textfield.TextInputEditText
import java.net.HttpURLConnection
import java.net.URL
import kotlin.text.Charsets.UTF_8

class LoginActivity : AppCompatActivity() {
    private lateinit var usernameInput: TextInputEditText
    private lateinit var passwordInput: TextInputEditText
    private lateinit var loginButton: Button
    private lateinit var progressBar: ProgressBar
    private lateinit var errorMessage: TextView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_login)

        usernameInput = findViewById(R.id.usernameInput)
        passwordInput = findViewById(R.id.passwordInput)
        loginButton = findViewById(R.id.loginButton)
        progressBar = findViewById(R.id.loginProgress)
        errorMessage = findViewById(R.id.loginError)

        val incomingMessage = intent.getStringExtra(EXTRA_MESSAGE)?.trim()
        if (!incomingMessage.isNullOrBlank()) {
            showError(incomingMessage)
        }

        loginButton.setOnClickListener {
            submitLogin()
        }
    }

    private fun submitLogin() {
        val username = usernameInput.text?.toString()?.trim().orEmpty()
        val password = passwordInput.text?.toString()?.trim().orEmpty()

        if (username.isBlank() || password.isBlank()) {
            showError(getString(R.string.login_error_empty))
            return
        }

        showLoading(true)
        clearError()

        Thread {
            val authResult = validateCredentials(username, password)
            runOnUiThread {
                showLoading(false)
                if (authResult.success) {
                    AdminSession.saveCredentials(this, username, password)
                    openMain()
                } else {
                    showError(authResult.message)
                }
            }
        }.start()
    }

    private fun validateCredentials(username: String, password: String): AuthResult {
        return try {
            val connection = (URL(AdminSession.ADMIN_URL).openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                connectTimeout = 12000
                readTimeout = 12000
                instanceFollowRedirects = false
                setRequestProperty("Authorization", "Basic ${encodeBasic(username, password)}")
                setRequestProperty("User-Agent", "BombaCloud-Android-Login")
            }
            val code = connection.responseCode
            connection.disconnect()
            when {
                code in 200..399 -> AuthResult(success = true, message = "")
                code == 401 || code == 403 -> AuthResult(success = false, message = getString(R.string.login_error_invalid))
                code == 429 -> AuthResult(success = false, message = getString(R.string.login_error_too_many_attempts))
                else -> AuthResult(success = false, message = getString(R.string.login_error_server, code))
            }
        } catch (_: Exception) {
            AuthResult(success = false, message = getString(R.string.login_error_network))
        }
    }

    private fun encodeBasic(username: String, password: String): String {
        val raw = "$username:$password".toByteArray(UTF_8)
        return Base64.encodeToString(raw, Base64.NO_WRAP)
    }

    private fun showLoading(loading: Boolean) {
        progressBar.visibility = if (loading) View.VISIBLE else View.GONE
        loginButton.isEnabled = !loading
        usernameInput.isEnabled = !loading
        passwordInput.isEnabled = !loading
    }

    private fun showError(message: String) {
        errorMessage.text = message
        errorMessage.visibility = View.VISIBLE
    }

    private fun clearError() {
        errorMessage.text = ""
        errorMessage.visibility = View.GONE
    }

    private fun openMain() {
        startActivity(
            Intent(this, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            }
        )
        finish()
    }

    private data class AuthResult(
        val success: Boolean,
        val message: String
    )

    companion object {
        const val EXTRA_MESSAGE = "login_message"
    }
}
