package com.koraexpress.ops_mobile

import android.app.Activity
import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.pm.PackageInfo
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.provider.Settings
import android.util.Log
import androidx.core.content.FileProvider
import io.flutter.plugin.common.BinaryMessenger
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.io.File

/**
 * Canal natif de la mise à jour hors store (MEZ-150) — l'app convoyeur n'est
 * pas publiée sur le Play Store : l'APK est téléchargé depuis KORA OPS Web
 * puis installé ici, via l'installeur système Android.
 *
 * Méthodes exposées à Dart (`ApkInstaller` côté Flutter) :
 *  - `installedVersion` → `{ versionName, versionCode }` de l'app installée ;
 *  - `cacheDirectory`   → répertoire où Dart doit écrire l'APK
 *                         (`cacheDir/apk_updates`, partagé par le FileProvider) ;
 *  - `canRequestPackageInstalls` → Android 8+ : l'utilisateur a-t-il autorisé
 *                         KORA OPS à installer des applications ?
 *  - `openInstallPermissionSettings` → écran système « Installer des applis
 *                         inconnues » pour KORA OPS ;
 *  - `installApk(path)` → lance l'installeur système sur le fichier (déjà
 *                         téléchargé ET vérifié en sha256 par Dart).
 *
 * L'installation reste confirmée par l'utilisateur (écran système) : une app
 * tierce ne peut pas s'installer silencieusement sans être propriétaire de
 * l'appareil (Device Owner).
 */
class ApkInstallerChannel(private val activity: Activity, messenger: BinaryMessenger) {
    private val logTag = "KoraApkInstaller"
    private val channel = MethodChannel(messenger, CHANNEL_NAME)

    init {
        channel.setMethodCallHandler(::onMethodCall)
    }

    private fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        try {
            when (call.method) {
                "installedVersion" -> result.success(installedVersion())
                "cacheDirectory" -> result.success(updatesDirectory().absolutePath)
                "canRequestPackageInstalls" -> result.success(canRequestPackageInstalls())
                "openInstallPermissionSettings" -> result.success(openInstallPermissionSettings())
                "installApk" -> {
                    val path = call.argument<String>("path")
                    if (path.isNullOrBlank()) {
                        result.error("invalid_path", "Chemin de l'APK manquant.", null)
                    } else {
                        result.success(installApk(File(path)))
                    }
                }
                else -> result.notImplemented()
            }
        } catch (error: Exception) {
            Log.e(logTag, "${call.method} a échoué", error)
            result.error("apk_installer_error", error.message, null)
        }
    }

    private fun installedVersion(): Map<String, Any?> {
        val info = packageInfo()
        val versionCode = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            info.longVersionCode
        } else {
            @Suppress("DEPRECATION")
            info.versionCode.toLong()
        }

        return mapOf(
            "versionName" to info.versionName,
            "versionCode" to versionCode,
        )
    }

    private fun packageInfo(): PackageInfo {
        val packageManager = activity.packageManager
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            packageManager.getPackageInfo(activity.packageName, PackageManager.PackageInfoFlags.of(0))
        } else {
            @Suppress("DEPRECATION")
            packageManager.getPackageInfo(activity.packageName, 0)
        }
    }

    private fun updatesDirectory(): File =
        File(activity.cacheDir, UPDATES_DIRECTORY).apply { mkdirs() }

    private fun canRequestPackageInstalls(): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.O ||
            activity.packageManager.canRequestPackageInstalls()

    private fun openInstallPermissionSettings(): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return true

        val intent = Intent(
            Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
            Uri.parse("package:${activity.packageName}"),
        )

        return try {
            activity.startActivity(intent)
            true
        } catch (_: ActivityNotFoundException) {
            // Certains firmwares (terminaux PDA) n'exposent pas cet écran
            // dédié : repli sur les paramètres de sécurité généraux.
            try {
                activity.startActivity(Intent(Settings.ACTION_SECURITY_SETTINGS))
                true
            } catch (_: ActivityNotFoundException) {
                false
            }
        }
    }

    /**
     * Retourne `installer_started`, `permission_required` ou lève une
     * erreur. Le fichier doit se trouver dans [updatesDirectory] (seul
     * dossier partagé par le FileProvider, voir res/xml/apk_installer_paths.xml).
     */
    private fun installApk(file: File): String {
        require(file.exists()) { "APK introuvable : ${file.absolutePath}" }
        require(file.canonicalPath.startsWith(updatesDirectory().canonicalPath)) {
            "L'APK doit se trouver dans le répertoire des mises à jour."
        }

        if (!canRequestPackageInstalls()) return "permission_required"

        val uri = FileProvider.getUriForFile(activity, "${activity.packageName}.apk_installer", file)
        val intent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(uri, APK_MIME_TYPE)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }

        activity.startActivity(intent)
        return "installer_started"
    }

    fun dispose() {
        channel.setMethodCallHandler(null)
    }

    companion object {
        const val CHANNEL_NAME = "kora.ops/apk_installer"
        private const val UPDATES_DIRECTORY = "apk_updates"
        private const val APK_MIME_TYPE = "application/vnd.android.package-archive"
    }
}
