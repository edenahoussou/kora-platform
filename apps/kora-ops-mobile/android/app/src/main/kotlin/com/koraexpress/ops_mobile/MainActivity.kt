package com.koraexpress.ops_mobile

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.core.content.ContextCompat
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

/**
 * Canal natif du scanner laser/imageur intégré du terminal PDA (« P501 » —
 * appareil générique, application pré-installée « Scan Tools », voir
 * document.md § Adaptateurs P501).
 *
 * Contrat CONFIRMÉ (vu directement dans les réglages Scan Tools de
 * l'appareil, capture d'écran du 24/09/2026) : action de diffusion
 * "scan.rcv.message" — mais le NOM DE L'EXTRA contenant la donnée décodée
 * varie selon le firmware exact et n'a pas pu être confirmé de la même
 * façon (une seule source, pour un appareil proche mais pas identique).
 *
 * Sur le terminal : Scan Tools → Settings → Output Mode → « Broadcast
 * Mode » (ou « Broadcast+Inputbox Mode » pour garder Scan Tools lui-même
 * utilisable) — sans ça, cette diffusion n'est jamais émise.
 *
 * [extractBarcodeValue] essaie plusieurs noms d'extra courants plutôt
 * qu'un seul supposé bon (voir échange du 24/09/2026 : le premier essai,
 * "barocode", n'a rien donné) — ET [logIntentExtras] journalise le
 * contenu BRUT de chaque diffusion reçue (visible dans `flutter run` ou
 * `adb logcat`, tag "KoraP501Scanner") : si aucune diffusion n'est reçue
 * du tout, le réglage Output Mode ci-dessus est le premier suspect ; si
 * une diffusion arrive mais qu'aucun des noms essayés ne correspond, le
 * journal montre le VRAI nom à ajouter à [byteArrayExtraCandidates]/
 * [stringExtraCandidates].
 *
 * Le côté Dart ([P501BarcodeScanner]) appelle `scan` sur le MethodChannel
 * et ATTEND un retour synchrone (un `String`) — alors que la donnée arrive
 * de façon asynchrone via ce broadcast. [startScan] fait le pont : elle
 * enregistre un récepteur à usage unique, attend la prochaine diffusion (ou
 * un délai d'expiration), puis résout le `MethodChannel.Result` une seule
 * fois.
 */
class MainActivity : FlutterActivity() {
    private val logTag = "KoraP501Scanner"
    private val channelName = "kora.ops/p501_scanner"
    private val scanBroadcastAction = "scan.rcv.message"
    private val scanTimeoutMillis = 15_000L

    // Ordre d'essai : le premier qui donne une valeur non vide gagne — voir
    // la docstring de la classe. À réduire à la seule bonne clé une fois
    // confirmée dans les journaux, pour ne pas risquer de lire la mauvaise
    // donnée sur un futur modèle de terminal qui utiliserait CES noms pour
    // autre chose.
    // Ordre d'essai : le premier qui donne une valeur non vide gagne — voir
    // la docstring de la classe. Confirmé le 24/09/2026 sur l'appareil
    // réel : "barcodeData" est le champ String propre à utiliser en
    // priorité ; "barocode" (sans "a") est un byte[] sur cet appareil, pas
    // une String — l'essayer comme String ne fait rien planter (Android
    // l'intercepte) mais journalise un avertissement inutile, retiré de
    // cette liste pour cette raison.
    private val stringExtraCandidates =
        listOf("barcodeData", "barcode", "data", "scannerdata", "value", "scanResult", "SCAN_BARCODE1")
    private val byteArrayExtraCandidates =
        listOf("barocode", "barcode", "data", "scannerdata", "value")

    private var pendingResult: MethodChannel.Result? = null
    private var scanReceiver: BroadcastReceiver? = null
    private val timeoutHandler = Handler(Looper.getMainLooper())
    private var timeoutRunnable: Runnable? = null

    // Mise à jour hors store (MEZ-150) — voir ApkInstallerChannel.
    private var apkInstallerChannel: ApkInstallerChannel? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, channelName)
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "scan" -> startScan(result)
                    else -> result.notImplemented()
                }
            }

        apkInstallerChannel = ApkInstallerChannel(this, flutterEngine.dartExecutor.binaryMessenger)
    }

    private fun startScan(result: MethodChannel.Result) {
        // Un seul scan à la fois : un appel concurrent annule le
        // précédent plutôt que de laisser deux Result en attente (un seul
        // peut être répondu ; répondre deux fois au même appel plante).
        cancelPendingScan()
        pendingResult = result
        Log.i(logTag, "startScan: en attente de \"$scanBroadcastAction\" (${scanTimeoutMillis}ms)")

        val receiver = object : BroadcastReceiver() {
            override fun onReceive(context: Context?, intent: Intent?) {
                logIntentExtras(intent)
                finishScan(extractBarcodeValue(intent))
            }
        }
        scanReceiver = receiver

        // RECEIVER_EXPORTED : la diffusion vient d'une AUTRE app (Scan
        // Tools, package séparé) — RECEIVER_NOT_EXPORTED (comportement par
        // défaut recommandé par Android 13+) la bloquerait silencieusement.
        // ContextCompat gère la différence d'API selon la version Android
        // (l'appareil testé est en Android 10, mais ça reste correct si le
        // parc évolue vers des terminaux plus récents).
        ContextCompat.registerReceiver(
            this,
            receiver,
            IntentFilter(scanBroadcastAction),
            ContextCompat.RECEIVER_EXPORTED,
        )

        // Filet de sécurité : si aucune diffusion n'arrive (gâchette jamais
        // pressée, Scan Tools pas en mode Broadcast...), le bouton
        // "Scanner" de l'app ne doit jamais rester bloqué indéfiniment.
        val runnable = Runnable {
            Log.w(logTag, "startScan: expiré, aucune diffusion \"$scanBroadcastAction\" reçue")
            finishScan(null)
        }
        timeoutRunnable = runnable
        timeoutHandler.postDelayed(runnable, scanTimeoutMillis)
    }

    /**
     * Journalise l'action et CHAQUE extra brut d'une diffusion reçue,
     * avec son type — voir docstring de la classe. C'est la première
     * chose à regarder (`adb logcat -s $logTag` ou la console `flutter
     * run`) quand un scan renvoie vide malgré un appui confirmé sur la
     * gâchette.
     */
    private fun logIntentExtras(intent: Intent?) {
        val extras = intent?.extras
        if (extras == null) {
            Log.i(logTag, "onReceive: action=${intent?.action}, aucun extra")
            return
        }

        val summary = extras.keySet().joinToString(separator = " | ") { key ->
            @Suppress("DEPRECATION")
            val value = extras.get(key)
            val rendered = when (value) {
                is ByteArray -> "byte[${value.size}]=\"${String(value, Charsets.UTF_8).take(120)}\""
                else -> "$value"
            }
            "$key(${value?.javaClass?.simpleName})=$rendered"
        }
        Log.i(logTag, "onReceive: action=${intent.action} — $summary")
    }

    private fun extractBarcodeValue(intent: Intent?): String? {
        if (intent == null) return null

        for (key in stringExtraCandidates) {
            val value = intent.getStringExtra(key)
            if (!value.isNullOrBlank()) {
                Log.i(logTag, "extractBarcodeValue: trouvé via extra String \"$key\"")
                return sanitize(value)
            }
        }

        for (key in byteArrayExtraCandidates) {
            val bytes = intent.getByteArrayExtra(key) ?: continue
            val length = intent.getIntExtra("length", bytes.size).coerceIn(0, bytes.size)
            if (length <= 0) continue

            val decoded = String(bytes, 0, length, Charsets.UTF_8).trim()
            if (decoded.isNotEmpty()) {
                Log.i(logTag, "extractBarcodeValue: trouvé via extra byte[] \"$key\"")
                return sanitize(decoded)
            }
        }

        Log.w(logTag, "extractBarcodeValue: diffusion reçue mais aucune clé connue n'a donné de valeur")
        return null
    }

    /**
     * Retire les caractères de contrôle en tête (ex. un octet nul `\0`) —
     * observé le 24/09/2026 sur cet appareil : certaines diffusions
     * portent un ou plusieurs octets de contrôle avant le contenu réel.
     * Sûr pour un vrai billet KORA (jamais de caractère de contrôle dans
     * un payload base64url) comme pour une URL classique.
     */
    private fun sanitize(value: String): String =
        value.trim().trimStart { it.code < 0x20 }.trim()

    private fun finishScan(value: String?) {
        cancelPendingScan(keepResult = true)

        val result = pendingResult ?: return
        pendingResult = null
        result.success(value)
    }

    private fun cancelPendingScan(keepResult: Boolean = false) {
        scanReceiver?.let {
            try {
                unregisterReceiver(it)
            } catch (_: IllegalArgumentException) {
                // Déjà désenregistré (ex. délai d'expiration et diffusion
                // arrivés presque simultanément) — sans conséquence.
            }
        }
        scanReceiver = null

        timeoutRunnable?.let { timeoutHandler.removeCallbacks(it) }
        timeoutRunnable = null

        if (!keepResult) {
            pendingResult = null
        }
    }

    override fun onDestroy() {
        apkInstallerChannel?.dispose()
        apkInstallerChannel = null
        cancelPendingScan()
        super.onDestroy()
    }
}
