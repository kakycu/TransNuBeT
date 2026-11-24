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
import android.print.PrintAttributes
import android.print.PrintManager
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
import android.widget.LinearLayout
import android.widget.TextView
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import kotlin.concurrent.thread

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private var filePathCallback: ValueCallback<Array<Uri>>? = null
    private var offlineDialog: AlertDialog? = null
    private var retryDialog: AlertDialog? = null
    private val mainHandler = Handler(Looper.getMainLooper())
    private var lastTriedUrl: String = "file:///android_asset/index.html"

    private val remoteStartUrl = "https://unicorniosoftware.gt.tc/"
    private val localHomeUrl = "file:///android_asset/index.html"
    private val versionCheckUrl = "https://raw.githubusercontent.com/kakycu/TransNuBeT/main/android-apk/dist/version.json"
    private val versionPrefs = "app_version"
    private val dismissedVersionCodeKey = "dismissed_version_code"

    // Botón flotante «Cerrar aplicación» que se inyecta en cada página cargada
    // dentro de la WebView. No depende de que la página web lo incluya.
    private val exitButtonJs = """
        (function () {
          try {
            if (document.getElementById('unicornioExitBtn')) return;
            var b = document.createElement('button');
            b.type = 'button';
            b.id = 'unicornioExitBtn';
            b.setAttribute('aria-label', 'Cerrar aplicación');
            b.textContent = 'Cerrar aplicación';
            b.style.cssText = 'position:fixed;left:12px;bottom:calc(12px + env(safe-area-inset-bottom,0px));z-index:2147483000;padding:10px 14px;border-radius:8px;border:1px solid #495057;background:#343A40;color:#DEE2E6;font:600 13px/1.1 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;box-shadow:0 8px 20px rgba(0,0,0,.45);cursor:pointer;-webkit-tap-highlight-color:transparent;';
            b.onclick = function () {
              if (window.AndroidApp && AndroidApp.requestClose) { AndroidApp.requestClose(); }
            };
            (document.body || document.documentElement).appendChild(b);
          } catch (e) {}
        })();
    """.trimIndent()

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
        WindowCompat.setDecorFitsSystemWindows(window, false)
        setContentView(R.layout.activity_main)
        window.statusBarColor = Color.parseColor("#0A0F1A")
        window.navigationBarColor = Color.parseColor("#0A0F1A")
        WindowInsetsControllerCompat(window, window.decorView).isAppearanceLightStatusBars = false

        webView = findViewById(R.id.webView)
        swipeRefresh = findViewById(R.id.swipeRefresh)
        ViewCompat.setOnApplyWindowInsetsListener(swipeRefresh) { v, insets ->
            val bars = insets.getInsets(WindowInsetsCompat.Type.systemBars() or WindowInsetsCompat.Type.displayCutout())
            v.setPadding(bars.left, bars.top, bars.right, bars.bottom)
            insets
        }

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
        settings.setSupportMultipleWindows(true)
        settings.javaScriptCanOpenWindowsAutomatically = true
        settings.userAgentString = settings.userAgentString + " UnicornioApp/1.0"

        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)

        webView.addJavascriptInterface(WebAppBridge(), "AndroidApp")

        webView.webViewClient = object : WebViewClient() {
override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val url = request.url.toString()
                val uri = request.url
                val host = uri.host ?: ""
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
                    url.startsWith("tel:") || url.startsWith("mailto:") || url.startsWith("whatsapp:") ||
                    url.startsWith("intent:") || url.startsWith("tg:") || url.startsWith("market:") -> {
                        try { startActivity(Intent(Intent.ACTION_VIEW, uri)) } catch (_: Exception) {}
                        true
                    }
                    url.startsWith("http://") || url.startsWith("https://") -> {
                        try { startActivity(Intent(Intent.ACTION_VIEW, uri)) } catch (_: Exception) {
                            Toast.makeText(this@MainActivity, "No se pudo abrir el enlace externo", Toast.LENGTH_SHORT).show()
                        }
                        true
                    }
                    else -> {
                        try { startActivity(Intent(Intent.ACTION_VIEW, uri)) } catch (_: Exception) {}
                        true
                    }
                }
            }

            override fun onPageFinished(view: WebView, url: String) {
                swipeRefresh.isRefreshing = false
                if (!url.startsWith("file:///android_asset/offline.html")) {
                    hideOfflineUi()
                }
                view.evaluateJavascript("window.print=function(){if(window.AndroidApp&&AndroidApp.printPage){AndroidApp.printPage();}else{}};", null)
                view.evaluateJavascript(exitButtonJs, null)
            }

            override fun onReceivedError(
                view: WebView,
                request: WebResourceRequest,
                error: WebResourceError
            ) {
                if (request.isForMainFrame) {
                    val failingUrl = request.url.toString()
                    // No mostrar modal por fallos de carga en páginas locales empaquetadas.
                    if (failingUrl.startsWith("file:///android_asset/")) return
                    lastTriedUrl = failingUrl
                    showOfflineUi()
                }
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onCreateWindow(view: WebView?, isDialog: Boolean, isUserGesture: Boolean, resultMsg: android.os.Message?): Boolean {
                val transport = resultMsg?.obj as? WebView.WebViewTransport ?: return false
                val temp = WebView(this@MainActivity)
                temp.webViewClient = object : WebViewClient() {
                    override fun shouldOverrideUrlLoading(v: WebView, r: WebResourceRequest): Boolean {
                        val u = r.url.toString()
                        if (u.startsWith("file:///android_asset/") || u.startsWith(remoteStartUrl) || (r.url.host == Uri.parse(remoteStartUrl).host)) {
                            if (!u.startsWith("file:///android_asset/") && !isNetworkAvailable()) {
                                lastTriedUrl = u; showOfflineUi()
                            } else {
                                this@MainActivity.webView.loadUrl(u)
                            }
                        } else {
                            try { startActivity(Intent(Intent.ACTION_VIEW, r.url)) } catch (_: Exception) {}
                        }
                        return true
                    }
                }
                transport.webView = temp
                resultMsg.sendToTarget()
                return true
            }

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
            val current = webView.url ?: ""
            if (current.startsWith("file:///android_asset/")) {
                // Las páginas locales no necesitan conexión para recargarse.
                hideOfflineUi()
                webView.reload()
            } else if (isNetworkAvailable()) {
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
                    else -> showCloseAppDialog()
                }
            }
        })

        if (savedInstanceState == null) {
            webView.loadUrl(localHomeUrl)
        } else {
            webView.restoreState(savedInstanceState)
        }
        checkForAppUpdate()
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
        hideOfflineUi()
        mainHandler.postDelayed({
            if (isNetworkAvailable()) {
                val target = when {
                    lastTriedUrl.startsWith("file:///android_asset/offline.html") -> localHomeUrl
                    lastTriedUrl.isNotBlank() -> lastTriedUrl
                    else -> localHomeUrl
                }
                webView.loadUrl(target)
            } else {
                val current = webView.url ?: ""
                if (current.startsWith("file:///android_asset/offline.html")) {
                    webView.evaluateJavascript("window.__onRetryFailed && window.__onRetryFailed();", null)
                } else {
                    showOfflineUi()
                }
            }
        }, 1400)
    }

    private fun showOfflineUi() {
        swipeRefresh.isRefreshing = false
        hideOfflineUi()
        if (webView.url?.startsWith("file:///android_asset/offline.html") != true) {
            webView.loadUrl("file:///android_asset/offline.html")
        }
    }

    private fun hideOfflineUi() {
        retryDialog?.dismiss()
        offlineDialog?.dismiss()
    }

    private fun showOfflineDialog() {
        if (offlineDialog?.isShowing == true) return
        val view = LayoutInflater.from(this).inflate(R.layout.dialog_no_connection, null, false)
        view.findViewById<TextView>(R.id.offlineStatus).text = "Se requiere conexión a internet"
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

    private fun showCloseAppDialog() {
        if (isFinishing || isDestroyed) return
        val view = LayoutInflater.from(this).inflate(R.layout.dialog_close_app, null, false)
        val dialog = AlertDialog.Builder(this)
            .setView(view)
            .setCancelable(true)
            .create()
        dialog.window?.setBackgroundDrawable(ColorDrawable(Color.TRANSPARENT))
        view.findViewById<View>(R.id.btnCloseDialogX).setOnClickListener { dialog.dismiss() }
        view.findViewById<View>(R.id.btnCloseCancel).setOnClickListener { dialog.dismiss() }
        view.findViewById<View>(R.id.btnCloseConfirm).setOnClickListener {
            dialog.dismiss()
            finish()
        }
        dialog.show()
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

    private fun checkForAppUpdate() {
        if (!isNetworkAvailable()) return
        thread(name = "apk-update-check") {
            try {
                val bustUrl = versionCheckUrl + (if (versionCheckUrl.contains("?")) "&" else "?") + "t=" + System.currentTimeMillis()
                val conn = (URL(bustUrl).openConnection() as HttpURLConnection).apply {
                    connectTimeout = 6000
                    readTimeout = 6000
                    useCaches = false
                    setRequestProperty("Accept", "application/json")
                    setRequestProperty("Cache-Control", "no-cache")
                }
                val body = conn.inputStream.bufferedReader().use { it.readText() }
                conn.disconnect()
                val json = JSONObject(body)
                val latestCode = json.optInt("versionCode", -1)
                val latestName = json.optString("versionName", BuildConfig.VERSION_NAME)
                val apkUrl = json.optString("apkUrl", "https://github.com/kakycu/TransNuBeT/raw/main/android-apk/dist/EcosisUnicorn.apk")
                val changelog = json.optString("changelog", "")
                val required = json.optBoolean("required", false)
                val releasedAt = json.optString("releasedAt", "")
                val sizeBytes = json.optLong("sizeBytes", -1L)
                val minSupported = json.optInt("minSupportedVersionCode", -1)
                val currentCode = BuildConfig.VERSION_CODE
                if (latestCode <= currentCode) return@thread

                val prefs = getSharedPreferences(versionPrefs, MODE_PRIVATE)
                val dismissed = prefs.getInt(dismissedVersionCodeKey, -1)
                if (!required && dismissed == latestCode) return@thread

                val notes = extractUpdateNotes(json, latestCode, changelog)

                runOnUiThread {
                    if (isFinishing || isDestroyed) return@runOnUiThread
                    showAppUpdateDialog(
                        latestCode = latestCode,
                        latestName = latestName,
                        apkUrl = apkUrl,
                        required = required || (minSupported > currentCode),
                        releasedAt = releasedAt,
                        sizeBytes = sizeBytes,
                        notes = notes,
                        minSupported = minSupported
                    )
                }
            } catch (_: Exception) {
            }
        }
    }

    private fun extractUpdateNotes(json: JSONObject, latestCode: Int, fallback: String): List<Pair<String, List<String>>> {
        val result = mutableListOf<Pair<String, List<String>>>()
        try {
            val changes = json.optJSONArray("changes") ?: return if (fallback.isNotBlank()) listOf("Novedades" to listOf(fallback)) else emptyList()
            var target: JSONObject? = null
            for (i in 0 until changes.length()) {
                val c = changes.optJSONObject(i) ?: continue
                if (c.optInt("versionCode", -2) == latestCode) { target = c; break }
            }
            if (target == null) target = changes.optJSONObject(0)
            val novedades = jsonArrayToStrings(target?.optJSONArray("novedades"))
            val correcciones = jsonArrayToStrings(target?.optJSONArray("correcciones"))
            if (novedades.isNotEmpty()) result.add("Novedades" to novedades)
            if (correcciones.isNotEmpty()) result.add("Correcciones" to correcciones)
            if (result.isEmpty()) {
                val summary = target?.optString("summary", "") ?: ""
                val text = if (summary.isNotBlank()) summary else fallback
                if (text.isNotBlank()) result.add("Novedades" to listOf(text))
            }
        } catch (_: Exception) {
            if (fallback.isNotBlank()) result.add("Novedades" to listOf(fallback))
        }
        return result
    }

    private fun jsonArrayToStrings(arr: org.json.JSONArray?): List<String> {
        if (arr == null) return emptyList()
        val out = mutableListOf<String>()
        for (i in 0 until arr.length()) {
            val v = arr.optString(i, "").trim()
            if (v.isNotBlank()) out.add(v)
        }
        return out
    }

    private fun formatBytes(bytes: Long): String {
        if (bytes <= 0L) return ""
        val mb = bytes / (1024.0 * 1024.0)
        return String.format(java.util.Locale.US, "%.1f MB", mb)
    }

    private fun showAppUpdateDialog(
        latestCode: Int,
        latestName: String,
        apkUrl: String,
        required: Boolean,
        releasedAt: String,
        sizeBytes: Long,
        notes: List<Pair<String, List<String>>>,
        minSupported: Int
    ) {
        val view = LayoutInflater.from(this).inflate(R.layout.dialog_app_update, null, false)
        val title = view.findViewById<TextView>(R.id.updateTitle)
        val versions = view.findViewById<TextView>(R.id.updateVersions)
        val summary = view.findViewById<TextView>(R.id.updateSummary)
        val metaRow = view.findViewById<LinearLayout>(R.id.updateMetaRow)
        val sizeView = view.findViewById<TextView>(R.id.updateSize)
        val dateView = view.findViewById<TextView>(R.id.updateDate)
        val requiredView = view.findViewById<TextView>(R.id.updateRequired)
        val changesContainer = view.findViewById<LinearLayout>(R.id.updateChangesContainer)
        val minView = view.findViewById<TextView>(R.id.updateMinSupported)
        val btnDownload = view.findViewById<TextView>(R.id.btnUpdateDownload)
        val btnNotes = view.findViewById<TextView>(R.id.btnUpdateNotes)
        val btnClose = view.findViewById<android.widget.ImageButton>(R.id.btnUpdateClose)
        val btnLater = view.findViewById<TextView>(R.id.btnUpdateLater)

        val appName = getString(R.string.app_name)
        val latestLabel = "v$latestName"
        title.text = if (required) "$appName: actualizaci?n necesaria" else "$appName: ?Nueva versi?n $latestLabel disponible!"
        versions.text = "Instalada v${BuildConfig.VERSION_NAME} (Rev. ${BuildConfig.VERSION_CODE})  ?  Nueva $latestLabel (Rev. $latestCode)"

        val sizeText = formatBytes(sizeBytes)
        val hasMeta = sizeText.isNotBlank() || releasedAt.isNotBlank()
        metaRow.visibility = if (hasMeta) View.VISIBLE else View.GONE
        if (sizeText.isNotBlank()) { sizeView.text = "Tama?o: $sizeText"; sizeView.visibility = View.VISIBLE }
        if (releasedAt.isNotBlank()) { dateView.text = "Fecha: $releasedAt"; dateView.visibility = View.VISIBLE }

        if (required) {
            requiredView.visibility = View.VISIBLE
            btnLater.visibility = View.GONE
        }

        if (minSupported > BuildConfig.VERSION_CODE) {
            minView.text = "Esta versi?n requiere la app con Rev. $minSupported o superior."
            minView.visibility = View.VISIBLE
        }

        val flatItems = notes.flatMap { it.second }
        val sectionSummary = when {
            flatItems.isNotEmpty() -> "Esta versi?n incluye mejoras y novedades para tu app."
            else -> "Hay una nueva versi?n disponible para descargar."
        }
        summary.text = sectionSummary
        summary.visibility = View.VISIBLE

        if (flatItems.isNotEmpty()) {
            val dp = resources.displayMetrics.density
            flatItems.take(6).forEach { item ->
                val row = TextView(this).apply {
                    text = "?  $item"
                    setTextColor(Color.parseColor("#CED4DA"))
                    textSize = 13f
                    setLineSpacing(2 * dp, 1f)
                    setPadding((4 * dp).toInt(), (4 * dp).toInt(), 0, (4 * dp).toInt())
                }
                changesContainer.addView(row)
            }
        }

        val dialog = AlertDialog.Builder(this)
            .setView(view)
            .setCancelable(!required)
            .create()
        dialog.window?.setBackgroundDrawable(ColorDrawable(Color.TRANSPARENT))

        btnDownload.setOnClickListener {
            try { startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(apkUrl))) }
            catch (_: Exception) { Toast.makeText(this, "No se pudo abrir la descarga", Toast.LENGTH_SHORT).show() }
            if (!required) dialog.dismiss()
        }
        btnNotes.setOnClickListener {
            dialog.dismiss()
            try { webView.loadUrl("file:///android_asset/releases.html") }
            catch (_: Exception) { webView.loadUrl("https://raw.githubusercontent.com/kakycu/TransNuBeT/main/android-apk/dist/releases.html") }
        }
        btnClose.setOnClickListener {
            if (required) {
                Toast.makeText(this, "Esta actualizaci?n es necesaria para continuar.", Toast.LENGTH_SHORT).show()
            } else {
                dialog.dismiss()
            }
        }
        btnLater.setOnClickListener { dialog.dismiss() }

        dialog.show()
    }


        @JavascriptInterface
        fun retryConnection() {
            runOnUiThread { this@MainActivity.retryConnection() }
        }

        @JavascriptInterface
        fun closeApp() {
            runOnUiThread { finish() }
        }

        @JavascriptInterface
        fun requestClose() {
            runOnUiThread { this@MainActivity.showCloseAppDialog() }
        }

        @JavascriptInterface
        fun isConnected(): Boolean = isNetworkAvailable()

        @JavascriptInterface
        fun getAppVersion(): String {
            return try {
                JSONObject()
                    .put("versionCode", BuildConfig.VERSION_CODE)
                    .put("versionName", BuildConfig.VERSION_NAME)
                    .put("packageName", BuildConfig.APPLICATION_ID)
                    .toString()
            } catch (_: Exception) {
                "{\"versionCode\":${BuildConfig.VERSION_CODE},\"versionName\":\"${BuildConfig.VERSION_NAME}\"}"
            }
        }

        @JavascriptInterface
        fun printPage() {
            runOnUiThread {
                try {
                    val printManager = getSystemService(Context.PRINT_SERVICE) as PrintManager
                    val jobName = "UnicornioSoftware_Documento"
                    val adapter = webView.createPrintDocumentAdapter(jobName)
                    printManager.print(jobName, adapter, PrintAttributes.Builder().build())
                } catch (_: Exception) {
                    Toast.makeText(this@MainActivity, "No se pudo iniciar la impresión", Toast.LENGTH_SHORT).show()
                }
            }
        }
    }
}

