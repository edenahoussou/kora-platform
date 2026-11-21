import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/datasources/colis_tracking_mock.dart';
import '../widgets/colis_status_chip.dart';

/// Écran 10 — « Mes envois & réceptions ».
///
/// Deux onglets, parce que ce ne sont pas les mêmes gestes : on suit ce
/// qu'on envoie, on va chercher ce qu'on reçoit. Les confondre ferait
/// chercher un code de retrait sur un colis qu'on a soi-même déposé.
class ColisShipmentsPage extends StatefulWidget {
  const ColisShipmentsPage({super.key});

  @override
  State<ColisShipmentsPage> createState() => _ColisShipmentsPageState();
}

class _ColisShipmentsPageState extends State<ColisShipmentsPage> {
  var _outgoing = true;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    final parcels = [
      for (final parcel in kColisTrackedParcels)
        if (parcel.outgoing == _outgoing) parcel,
    ];
    final incoming = kColisTrackedParcels
        .where((parcel) => !parcel.outgoing)
        .length;
    final outgoing = kColisTrackedParcels.length - incoming;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeaderless(title: l10n.colisShipmentsTitle),
              Row(
                children: [
                  Expanded(
                    child: _Tab(
                      label: l10n.colisSent(outgoing),
                      selected: _outgoing,
                      onTap: () => setState(() => _outgoing = true),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _Tab(
                      label: l10n.colisToReceive(incoming),
                      selected: !_outgoing,
                      onTap: () => setState(() => _outgoing = false),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              Expanded(
                child: parcels.isEmpty
                    ? Center(
                        child: Text(
                          l10n.colisShipmentsEmpty,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.body(koraColors.textMuted),
                        ),
                      )
                    : ListView(
                        padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                        children: [
                          for (final parcel in parcels) ...[
                            _ParcelTile(
                              parcel: parcel,
                              onTap: () => context.push(
                                AppRouter.colisTrackingPath,
                                extra: parcel.reference,
                              ),
                            ),
                            const SizedBox(height: 8),
                          ],
                        ],
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Un en-tête sobre : un retour et un titre, sans la marque.
///
/// Les écrans de suivi s'ouvrent par-dessus l'onglet Colis, qui porte
/// déjà le logo : le répéter deux fois de suite n'apporte rien.
class ScreenHeaderless extends StatelessWidget {
  const ScreenHeaderless({super.key, required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          KoraIconButton(
            icon: Icons.arrow_back_rounded,
            onTap: () => context.pop(),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: context.koraTitleColor,
                fontSize: 20,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Un onglet de la liste : envoyés, ou à recevoir.
class _Tab extends StatelessWidget {
  const _Tab({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Material(
      color: selected ? AppColors.kora : koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.pill),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.pill),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 11),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.pill),
            border: Border.all(
              color: selected ? AppColors.kora : koraColors.line,
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? AppColors.white : koraColors.textMuted,
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ),
    );
  }
}

/// Un envoi de la liste : son état, sa référence, son trajet.
class _ParcelTile extends StatelessWidget {
  const _ParcelTile({required this.parcel, required this.onTap});

  final ColisTrackedParcel parcel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(19),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(19),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(19),
            border: Border.all(color: koraColors.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  ColisStatusChip(status: parcel.status),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      parcel.reference,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.end,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                '${parcel.originCity} (${parcel.originHub})'
                '  →  '
                '${parcel.destinationCity} (${parcel.destinationHub})',
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 13,
                  height: 1.25,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      l10n.colisStepOfTotal(parcel.step, parcel.totalSteps),
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ),
                  Text(
                    parcel.status == ColisTrackingStatus.readyForPickup
                        ? l10n.colisReadySince(parcel.etaTime)
                        : l10n.colisEtaShort(parcel.etaTime),
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Icon(
                    Icons.chevron_right_rounded,
                    size: 18,
                    color: koraColors.textMuted,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
