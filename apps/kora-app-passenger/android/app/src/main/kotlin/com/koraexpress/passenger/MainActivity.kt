package com.koraexpress.passenger

import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import android.os.Bundle
import io.flutter.embedding.android.FlutterActivity

class MainActivity : FlutterActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        createNotificationChannel()
    }

    /**
     * Déclare le canal sur lequel arrivent les notifications KORA.
     *
     * Sans lui, Firebase retombe sur son canal de secours
     * (`fcm_fallback_notification_channel`), qui n'a pas de nom : le
     * voyageur voit alors « Divers » dans les réglages de son téléphone,
     * et ne peut pas distinguer les alertes de trajet du reste. Le canal
     * de secours arrive aussi en silence sur certains appareils.
     *
     * Le canal doit exister **avant** la première notification, et
     * `AndroidManifest.xml` le désigne à Firebase par son identifiant.
     * Android ignore l'appel si le canal est déjà créé, donc le refaire à
     * chaque ouverture ne coûte rien.
     */
    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val channel = NotificationChannel(
            CHANNEL_ID,
            getString(R.string.notification_channel_name),
            NotificationManager.IMPORTANCE_HIGH,
        ).apply {
            description = getString(R.string.notification_channel_description)
            enableVibration(true)
        }

        getSystemService(NotificationManager::class.java)
            ?.createNotificationChannel(channel)
    }

    private companion object {
        /** Doit correspondre à la méta-donnée de `AndroidManifest.xml`. */
        const val CHANNEL_ID = "kora_voyages"
    }
}
