package com.heidi.bookingadmin

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.view.View
import android.webkit.HttpAuthHandler
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.google.firebase.messaging.FirebaseMessaging
import java.net.HttpURLConnection
import java.net.URL

class MainActivity : AppCompatActivity() {
    private lateinit var webView: WebView
    private lateinit var offlineMessage: TextView

    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { _ -> }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.adminWebView)
        offlineMessage = findViewById(R.id.offlineMessage)

        setupWebView()
        loadAdminUrl(forceFresh = true)

        requestNotificationPermissionIfNeeded()
        registerFcmToken()
    }

    private fun setupWebView() {
        val settings = webView.settings
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
        settings.useWideViewPort = true
        settings.loadWithOverviewMode = true
        settings.cacheMode = WebSettings.LOAD_NO_CACHE

        webView.clearCache(true)

        webView.webViewClient = object : WebViewClient() {
            override fun onReceivedHttpAuthRequest(
                view: WebView?,
                handler: HttpAuthHandler?,
                host: String?,
                realm: String?
            ) {
                handler?.proceed(ADMIN_USER, ADMIN_PASS)
            }

            override fun onReceivedError(
                view: WebView,
                request: WebResourceRequest,
                error: WebResourceError
            ) {
                if (request.isForMainFrame) {
                    offlineMessage.visibility = View.VISIBLE
                    webView.visibility = View.GONE
                }
            }

            override fun onPageFinished(view: WebView, url: String) {
                offlineMessage.visibility = View.GONE
                webView.visibility = View.VISIBLE
            }
        }
    }

    private fun requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) return
        val permission = Manifest.permission.POST_NOTIFICATIONS
        val granted = ContextCompat.checkSelfPermission(this, permission) == PackageManager.PERMISSION_GRANTED
        if (!granted) {
            requestPermissionLauncher.launch(permission)
        }
    }

    private fun registerFcmToken() {
        FirebaseMessaging.getInstance().token
            .addOnSuccessListener { token ->
                Thread {
                    postToken(token)
                }.start()
            }
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

    override fun onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack()
        } else {
            super.onBackPressed()
        }
    }

    override fun onResume() {
        super.onResume()
        loadAdminUrl(forceFresh = true)
    }

    private fun loadAdminUrl(forceFresh: Boolean = false) {
        val url = if (forceFresh) {
            "$ADMIN_URL?v=${System.currentTimeMillis()}"
        } else {
            ADMIN_URL
        }
        webView.loadUrl(url)
    }

    companion object {
        private const val ADMIN_URL = "https://heidivanhorny.com/booking/admin/"
        private const val TOKEN_ENDPOINT = "https://heidivanhorny.com/booking/api/admin/push-token.php"
        private const val ADMIN_USER = "capitainecommando"
        private const val ADMIN_PASS = "Simo.666$$$"
    }
}
