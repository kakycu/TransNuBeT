package com.unicorniosoftware.app

import android.annotation.SuppressLint
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.graphics.drawable.ColorDrawable
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.net.Uri
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.LayoutInflater
import android.view.View
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.TextView
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private var filePathCallback: ValueCallback<Array<Uri>>? = null
    private var offlineDialog: AlertDialog? = null
    private var retryDialog: AlertDialog? = null
    private val mainHandler = Handler(Looper.getMainLooper())
    private var lastTriedUrl: String = "file:///android_asset/index.html"

    private val remoteStartUrl = "https://unicorniosoftware.gt.tc/"
    private val localIntroUrl = "file:///android_asset/intro.html"
    private val localHomeUrl = "file:///android_asset/index.html"

    private val fileChooserLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        val data = result.data
        val uris = if (result.resultCode == RESULT_OK) {
            WebChromeClient.FileChooserParams.parseResult(result.resultCode, data)
        } else {
            null
        }
        filePathCallback?.onReceiveValue(uris)
        filePathCallback = null
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webView)
        swipeRefresh = findViewById(R.id.swipeRefresh)

        val settings = webView.settings
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.databaseEnabled = true
        settings.loadsImagesAutomatically = true
        settings.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
        settings.useWideViewPort = true
        settings.loadWithOverviewMode = true
        settings.builtInZoomControls = false
        settings.displayZoomControls = false
        settings.allowFileAccess = true
        settings.allowContentAccess = true
        settings.userAgentString = settings.userAgentString + " UnicornioApp/1.0"

        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)

        webView.addJavascriptInterface(WebAppBridge(), "AndroidApp")

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val url = request.url.toString()
                val host = request.url.host ?: ""
                return when {
                    url.startsWith("file:///android_asset/") -> false
                    host == Uri.parse(remoteStartUrl).host || url.startsWith(remoteStartUrl) -> {
                        if (!isNetworkAvailable()) {
                            lastTriedUrl = url
                            showOfflineUi()
                            true
                        } else {
                            hideOfflineUi()
                            lastTriedUrl = url
                            false
                        }
                    }
                    url.startsWith("tel:") || url.startsWith("mailto:") || url.startsWith("whatsapp:") -> {
                        try { startActivity(Intent(Intent.ACTION_VIEW, request.url)) } catch (_: Exception) {}
                        true
                    }
                    else -> {
                        try { startActivity(Intent(Intent.ACTION_VIEW, request.url)) } catch (_: Exception) {}
                        true
                    }
                }
            }

            override fun onPageFinished(view: WebView, url: String) {
                swipeRefresh.isRefreshing = false
                if (!url.startsWith("file:///android_asset/offline.html")) {
                    hideOfflineUi()
                }
            }

            override fun onReceivedError(
                view: WebView,
                request: WebResourceRequest,
                error: WebResourceError
            ) {
                if (request.isForMainFrame) {
                    lastTriedUrl = request.url.toString()
                    showOfflineUi()
                }
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onShowFileChooser(
                webView: WebView,
                callback: ValueCallback<Array<Uri>>,
                fileChooserParams: FileChooserParams
            ): Boolean {
                filePathCallback?.onReceiveValue(null)
                filePathCallback = callback
                return try {
                    fileChooserLauncher.launch(fileChooserParams.createIntent())
                    true
                } catch (e: ActivityNotFoundException) {
                    filePathCallback = null
                    Toast.makeText(this@MainActivity, "No se pudo abrir el selector de archivos", Toast.LENGTH_SHORT).show()
                    false
                }
            }
        }

        webView.setDownloadListener { url, _, _, _, _ ->
            try {
                startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
            } catch (_: Exception) {
                Toast.makeText(this, "No se pudo iniciar la descarga", Toast.LENGTH_SHORT).show()
            }
        }

        swipeRefresh.setOnRefreshListener {
            if (isNetworkAvailable()) {
                hideOfflineUi()
                webView.reload()
            } else {
                swipeRefresh.isRefreshing = false
                showOfflineUi()
            }
        }

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                when {
                    offlineDialog?.isShowing == true -> offlineDialog?.dismiss()
                    webView.canGoBack() -> webView.goBack()
                    else -> finish()
                }
            }
        })

        if (savedInstanceState == null) {
            webView.loadUrl(localIntroUrl)
        } else {
            webView.restoreState(savedInstanceState)
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        webView.saveState(outState)
    }

    private fun isNetworkAvailable(): Boolean {
        val cm = getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        val network = cm.activeNetwork ?: return false
        val caps = cm.getNetworkCapabilities(network) ?: return false
        return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    private fun retryConnection() {
        if (offlineDialog?.isShowing == true) offlineDialog?.dismiss()
        showRetryDialog()
        mainHandler.postDelayed({
            retryDialog?.dismiss()
            if (isNetworkAvailable()) {
                hideOfflineUi()
                val target = when {
                    lastTriedUrl.startsWith("file:///android_asset/offline.html") -> localHomeUrl
                    lastTriedUrl.startsWith("file:///android_asset/intro.html") -> localHomeUrl
                    lastTriedUrl.isNotBlank() -> lastTriedUrl
                    else -> localHomeUrl
                }
                webView.loadUrl(target)
            } else {
                showOfflineUi()
            }
        }, 1400)
    }

    private fun showOfflineUi() {
        swipeRefresh.isRefreshing = false
        if (webView.url?.startsWith("file:///android_asset/intro.html") == true) {
            return
        }
        if (webView.url?.startsWith("file:///android_asset/offline.html") != true) {
            webView.loadUrl("file:///android_asset/offline.html")
        }
        showOfflineDialog()
    }

    private fun hideOfflineUi() {
        retryDialog?.dismiss()
        offlineDialog?.dismiss()
    }

    private fun showOfflineDialog() {
        if (offlineDialog?.isShowing == true) return
        val view = LayoutInflater.from(this).inflate(R.layout.dialog_no_connection, null, false)
        view.findViewById<TextView>(R.id.offlineStatus).text = "UnicornioSoftware · Se requiere internet"
        view.findViewById<View>(R.id.btnOfflineRetry).setOnClickListener {
            offlineDialog?.dismiss()
            retryConnection()
        }
        view.findViewById<View>(R.id.btnOfflineClose).setOnClickListener {
            offlineDialog?.dismiss()
            finish()
        }

        offlineDialog = AlertDialog.Builder(this)
            .setView(view)
            .setCancelable(false)
            .create()
        offlineDialog?.window?.setBackgroundDrawable(ColorDrawable(Color.TRANSPARENT))
        offlineDialog?.show()
    }

    private fun showRetryDialog() {
        if (retryDialog?.isShowing == true) return
        val view = LayoutInflater.from(this).inflate(R.layout.dialog_retrying, null, false)
        view.findViewById<TextView>(R.id.retryStatus).text = "Comprobando tu conexión a internet..."
        retryDialog = AlertDialog.Builder(this)
            .setView(view)
            .setCancelable(false)
            .create()
        retryDialog?.window?.setBackgroundDrawable(ColorDrawable(Color.TRANSPARENT))
        retryDialog?.show()
    }

    inner class WebAppBridge {
        @JavascriptInterface
        fun retryConnection() {
            runOnUiThread { this@MainActivity.retryConnection() }
        }

        @JavascriptInterface
        fun closeApp() {
            runOnUiThread { finish() }
        }

        @JavascriptInterface
        fun isConnected(): Boolean = isNetworkAvailable()
    }
}
