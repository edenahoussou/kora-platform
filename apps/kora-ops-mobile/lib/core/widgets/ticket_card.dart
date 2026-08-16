import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/app_text_styles.dart';
import '../../app/theme/kora_colors.dart';
import '../../features/ops/domain/entities/ticket.dart';

/// Billet affiché en app — reprend le contenu requis par MEZ-10 (header
/// KORA, infos départ, passager, siège, QR code, footer fiscal) dans une
/// présentation "carte d'embarquement" professionnelle, intégrée au design
/// system (pas le rendu monospace 58mm — celui-ci reste réservé à
/// l'impression physique, voir `TicketFormatter` et `PrinterTestPage`).
class TicketCard extends StatelessWidget {
  const TicketCard({
    required this.ticket,
    required this.passengerName,
    required this.route,
    required this.date,
    required this.heure,
    super.key,
  });

  final Ticket ticket;
  final String passengerName;
  final String route;
  final String date;
  final String heure;

  static const String _fiscalFooter =
      'Merci de votre confiance — KORA EXPRESS · NIF 3202600123456';

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;

    return Material(
      elevation: 6,
      shadowColor: Colors.black26,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        child: Container(
          color: koraColors.surface,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              // --- Header KORA ---
              Container(
                padding: const EdgeInsets.all(AppSpacing.md),
                decoration: const BoxDecoration(color: AppColors.kora),
                child: Row(
                  children: <Widget>[
                    Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: AppColors.white,
                        borderRadius: BorderRadius.circular(AppRadius.sm),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: Image.asset('assets/images/kora_logo.png',
                            height: 22, fit: BoxFit.contain),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text('KORA EXPRESS',
                              style: AppTextStyles.eyebrow(AppColors.gold)),
                          Text(
                            route,
                            style: const TextStyle(
                                color: AppColors.white,
                                fontWeight: FontWeight.w700,
                                fontSize: 14),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              // --- Infos départ / passager / siège ---
              Padding(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.md, AppSpacing.md, AppSpacing.md, AppSpacing.sm),
                child: Row(
                  children: <Widget>[
                    _TicketField(
                        label: 'PASSAGER',
                        value: passengerName,
                        koraColors: koraColors,
                        flex: 2),
                    _TicketField(
                        label: 'SIÈGE',
                        value: ticket.siege,
                        koraColors: koraColors),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
                child: Row(
                  children: <Widget>[
                    _TicketField(
                        label: 'DATE', value: date, koraColors: koraColors),
                    _TicketField(
                        label: 'HEURE', value: heure, koraColors: koraColors),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.md, AppSpacing.sm, AppSpacing.md, 0),
                child: Row(
                  children: <Widget>[
                    _TicketField(
                        label: 'ORIGINE',
                        value: ticket.segmentOrigine,
                        koraColors: koraColors),
                    _TicketField(
                        label: 'DESTINATION',
                        value: ticket.segmentDestination,
                        koraColors: koraColors),
                  ],
                ),
              ),
              // --- Séparation type "billet à détacher" ---
              _PerforatedDivider(color: koraColors.background),
              // --- QR code ---
              Padding(
                padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
                child: Column(
                  children: <Widget>[
                    Container(
                      padding: const EdgeInsets.all(AppSpacing.sm),
                      decoration: BoxDecoration(
                        border: Border.all(color: koraColors.line),
                        borderRadius: BorderRadius.circular(AppRadius.md),
                      ),
                      child: QrImageView(
                        data: ticket.qrData,
                        version: QrVersions.auto,
                        size: 140,
                        gapless: true,
                        eyeStyle: const QrEyeStyle(
                            eyeShape: QrEyeShape.square, color: AppColors.kora),
                        dataModuleStyle: const QrDataModuleStyle(
                          dataModuleShape: QrDataModuleShape.square,
                          color: AppColors.kora,
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Text(
                      ticket.numero,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 11,
                        fontFamily: 'monospace',
                        letterSpacing: 1,
                      ),
                    ),
                  ],
                ),
              ),
              // --- Footer fiscal ---
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(
                    vertical: AppSpacing.sm, horizontal: AppSpacing.md),
                color: koraColors.background,
                child: Text(
                  _fiscalFooter,
                  textAlign: TextAlign.center,
                  style: TextStyle(color: koraColors.textMuted, fontSize: 9),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TicketField extends StatelessWidget {
  const _TicketField(
      {required this.label,
      required this.value,
      required this.koraColors,
      this.flex = 1});

  final String label;
  final String value;
  final KoraColors koraColors;
  final int flex;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      flex: flex,
      child: Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(label, style: AppTextStyles.eyebrow(koraColors.textMuted)),
            const SizedBox(height: 2),
            Text(
              value,
              style: TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                  color: koraColors.textPrimary),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}

/// Ligne pointillée avec deux encoches semi-circulaires, imitant la
/// séparation "à détacher" d'un billet physique.
class _PerforatedDivider extends StatelessWidget {
  const _PerforatedDivider({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 20,
      child: Stack(
        alignment: Alignment.center,
        clipBehavior: Clip.none,
        children: <Widget>[
          Row(
            children: List<Widget>.generate(
              24,
              (int index) => Expanded(
                child: Container(
                  height: 1.5,
                  margin: const EdgeInsets.symmetric(horizontal: 2),
                  color: index.isEven ? Colors.transparent : Colors.black12,
                ),
              ),
            ),
          ),
          Positioned(
            left: -10,
            child: Container(
                width: 20,
                height: 20,
                decoration:
                    BoxDecoration(color: color, shape: BoxShape.circle)),
          ),
          Positioned(
            right: -10,
            child: Container(
                width: 20,
                height: 20,
                decoration:
                    BoxDecoration(color: color, shape: BoxShape.circle)),
          ),
        ],
      ),
    );
  }
}
