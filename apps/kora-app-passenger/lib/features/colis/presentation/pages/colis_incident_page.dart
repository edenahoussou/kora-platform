import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../../data/datasources/colis_tracking_mock.dart';
import '../widgets/colis_surface.dart';
import 'colis_shipments_page.dart';

/// Ce qui a pu arriver au colis, et qui décide de l'instruction.
///
/// Trois motifs, pas un champ libre : la cellule litiges ne traite pas un
/// colis introuvable comme un emballage écrasé, et c'est le motif qui
/// oriente le dossier dès son ouverture.
enum ColisIncidentReason {
  /// Colis introuvable, non localisé au hub intermédiaire.
  loss,

  /// Emballage ou scellé détérioré, écrasé, ouvert.
  damage,

  /// Anomalie de remise : refus au guichet, transfert non conforme.
  custody,
}

/// Écran — « Signalement d'incident ».
///
/// Le seul écran du module qui ouvre un dossier plutôt que d'en suivre
/// un. Il se lit depuis le suivi, quand ce que montre le suivi ne
/// correspond plus à ce que le voyageur constate.
///
/// **Aucune requête n'est envoyée** : aucun endpoint `parcel` n'existe,
/// et le ticket l'interdit. Le récépissé affiché après l'envoi est un
/// aperçu de ce que la cellule litiges renverra.
class ColisIncidentPage extends StatefulWidget {
  const ColisIncidentPage({super.key, this.reference});

  /// La référence du colis concerné. Sans elle, l'écran ouvre le
  /// premier envoi suivi — un signalement sans colis n'a pas de sens.
  final String? reference;

  @override
  State<ColisIncidentPage> createState() => _ColisIncidentPageState();
}

class _ColisIncidentPageState extends State<ColisIncidentPage> {
  static const int _maxLength = 500;

  /// En deçà, la cellule litiges rappelle pour demander des précisions,
  /// et le dossier perd les 24 h qu'on promet.
  static const int _minLength = 40;

  final _description = TextEditingController();

  ColisIncidentReason? _reason;
  var _sent = false;

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  bool get _canSend =>
      _reason != null && _description.text.trim().length >= _minLength;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final parcel = colisParcelByReference(widget.reference ?? '');

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeaderless(
                eyebrow: l10n.colisIncidentEyebrow,
                title: l10n.colisIncidentTitle,
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                  children: [
                    Text(
                      l10n.colisIncidentLead,
                      style: AppTextStyles.body(koraColors.textMuted),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    // Le colis concerné, rappelé : un signalement sans
                    // référence n'est pas instruisible.
                    _ParcelRecap(parcel: parcel),
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      l10n.colisIncidentReasonLabel,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      l10n.colisIncidentSingleChoice,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                    const SizedBox(height: 9),
                    for (final reason in ColisIncidentReason.values) ...[
                      _ReasonTile(
                        reason: reason,
                        selected: _reason == reason,
                        onTap: () => setState(() => _reason = reason),
                      ),
                      const SizedBox(height: 8),
                    ],
                    const SizedBox(height: AppSpacing.sm),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            l10n.colisIncidentDescriptionLabel,
                            style: TextStyle(
                              color: context.koraTitleColor,
                              fontSize: 14,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        Text(
                          '${_description.text.characters.length}'
                          '/$_maxLength',
                          style: AppTextStyles.caption(koraColors.textMuted),
                        ),
                      ],
                    ),
                    const SizedBox(height: 7),
                    // Un `TextField` nu plutôt qu'un `KoraInput` : le
                    // composant du design system ne tient qu'une ligne,
                    // et une description d'incident en demande cinq.
                    TextField(
                      controller: _description,
                      maxLines: 5,
                      maxLength: _maxLength,
                      onChanged: (_) => setState(() {}),
                      style: AppTextStyles.body(context.koraTitleColor),
                      decoration: InputDecoration(
                        counterText: '',
                        hintText: l10n.colisIncidentDescriptionHint,
                        filled: true,
                        fillColor: koraColors.surface,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(AppRadius.md),
                          borderSide: BorderSide(color: koraColors.line),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(AppRadius.md),
                          borderSide: BorderSide(color: koraColors.line),
                        ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    // Pourquoi on demande du détail : c'est lui qui
                    // permet l'instruction immédiate, sans rappel.
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          _canSend
                              ? Icons.check_circle_outline_rounded
                              : Icons.info_outline_rounded,
                          size: 15,
                          color: _canSend
                              ? AppColors.success
                              : koraColors.textMuted,
                        ),
                        const SizedBox(width: 7),
                        Expanded(
                          child: Text(
                            l10n.colisIncidentDescriptionHelp,
                            style: AppTextStyles.caption(koraColors.textMuted),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                    ColisNote(
                      icon: Icons.verified_outlined,
                      title: l10n.colisGuaranteeTitle,
                      message: l10n.colisGuaranteeText,
                    ),
                    // Le récépissé n'apparaît qu'après l'envoi : avant,
                    // il n'y a pas de dossier, et en montrer un serait
                    // promettre une prise en charge qui n'existe pas.
                    const SizedBox(height: AppSpacing.md),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            l10n.colisClaimOpened.toUpperCase(),
                            style: AppTextStyles.eyebrow(koraColors.textMuted),
                          ),
                        ),
                        // Tant que rien n'est envoyé, l'accusé est un
                        // exemple — et le cartouche le dit, pour qu'on
                        // ne cherche pas un dossier qui n'existe pas.
                        if (!_sent)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: koraColors.background,
                              borderRadius: BorderRadius.circular(
                                AppRadius.pill,
                              ),
                            ),
                            child: Text(
                              l10n.colisClaimSample,
                              style: TextStyle(
                                color: koraColors.textMuted,
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Opacity(
                      opacity: _sent ? 1 : 0.6,
                      child: _Receipt(parcel: parcel),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _ClaimsSupport(),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: KoraButton(
                  label: _sent
                      ? l10n.colisIncidentSent
                      : l10n.colisIncidentSend,
                  icon: Icons.send_rounded,
                  iconPosition: KoraButtonIconPosition.edges,
                  // Inerte tant que le dossier n'est pas instruisible :
                  // un signalement sans motif ni détail revient par
                  // téléphone, et perd les 24 h promises.
                  onPressed: _canSend && !_sent
                      ? () => setState(() => _sent = true)
                      : null,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Le colis concerné par le signalement.
class _ParcelRecap extends StatelessWidget {
  const _ParcelRecap({required this.parcel});

  final ColisTrackedParcel parcel;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Icon(
                Icons.inventory_2_outlined,
                size: 17,
                color: context.koraTitleColor,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisCertifiedParcel,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              KoraTag(
                label: l10n.colisLineTag(parcel.line),
                variant: KoraTagVariant.outline,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            l10n.colisTrackingNumber.toUpperCase(),
            style: AppTextStyles.eyebrow(koraColors.textMuted),
          ),
          const SizedBox(height: 2),
          Text(
            parcel.reference,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 15,
              fontWeight: FontWeight.w900,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
          const SizedBox(height: 10),
          Text(
            '${parcel.originCity} (${parcel.originHub})'
            '  →  '
            '${parcel.destinationCity} (${parcel.destinationHub})',
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 12,
              height: 1.3,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Icon(
                Icons.verified_user_outlined,
                size: 15,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  '${l10n.colisSender} : ${parcel.senderName}',
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Un motif de réclamation, avec ce qu'il recouvre.
class _ReasonTile extends StatelessWidget {
  const _ReasonTile({
    required this.reason,
    required this.selected,
    required this.onTap,
  });

  final ColisIncidentReason reason;
  final bool selected;
  final VoidCallback onTap;

  IconData get _icon => switch (reason) {
    ColisIncidentReason.loss => Icons.search_off_rounded,
    ColisIncidentReason.damage => Icons.broken_image_outlined,
    ColisIncidentReason.custody => Icons.swap_horizontal_circle_outlined,
  };

  String _title(AppLocalization l10n) => switch (reason) {
    ColisIncidentReason.loss => l10n.colisReasonLossTitle,
    ColisIncidentReason.damage => l10n.colisReasonDamageTitle,
    ColisIncidentReason.custody => l10n.colisReasonCustodyTitle,
  };

  String _text(AppLocalization l10n) => switch (reason) {
    ColisIncidentReason.loss => l10n.colisReasonLossText,
    ColisIncidentReason.damage => l10n.colisReasonDamageText,
    ColisIncidentReason.custody => l10n.colisReasonCustodyText,
  };

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Semantics(
      button: true,
      selected: selected,
      label: _title(l10n),
      child: Material(
        // Le choix retenu passe en gris, pas en doré : c'est une
        // sélection dans une liste, pas une action à déclencher.
        color: selected ? koraColors.background : koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.md),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadius.md),
              boxShadow: selected ? null : kColisCardShadow,
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Le bouton radio en tête de ligne : la maquette dit
                // d'emblée qu'un seul motif se choisit.
                Padding(
                  padding: const EdgeInsets.only(top: 1),
                  child: Icon(
                    selected
                        ? Icons.radio_button_checked_rounded
                        : Icons.radio_button_unchecked_rounded,
                    size: 20,
                    color: selected
                        ? context.koraTitleColor
                        : koraColors.textMuted,
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        children: [
                          Icon(_icon, size: 17, color: context.koraTitleColor),
                          const SizedBox(width: 7),
                          Expanded(
                            child: Text(
                              _title(l10n),
                              style: TextStyle(
                                color: context.koraTitleColor,
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        _text(l10n),
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// L'accusé de réception du signalement.
class _Receipt extends StatelessWidget {
  const _Receipt({required this.parcel});

  final ColisTrackedParcel parcel;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              const Icon(
                Icons.verified_rounded,
                size: 19,
                color: AppColors.success,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisClaimOpened,
                  style: const TextStyle(
                    color: AppColors.success,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          // Le numéro du dossier : c'est lui qu'on cite à
          // l'assistance, et il se lit avant l'explication.
          const SizedBox(height: 6),
          Text(
            colisNewIncidentNumber(DateTime.now()),
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 20,
              fontWeight: FontWeight.w900,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
          const SizedBox(height: 9),
          Text(
            l10n.colisClaimOpenedText(
              '${parcel.originCity} → ${parcel.destinationCity}',
            ),
            style: TextStyle(
              color: koraColors.textMuted,
              fontSize: 12,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            l10n.colisClaimContact(parcel.senderName),
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
          const SizedBox(height: 2),
          Text('', style: AppTextStyles.caption(koraColors.textMuted)),
        ],
      ),
    );
  }
}

/// L'assistance dédiée aux réclamations.
class _ClaimsSupport extends StatelessWidget {
  const _ClaimsSupport();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Row(
      children: [
        Icon(
          Icons.support_agent_rounded,
          size: 17,
          color: koraColors.textMuted,
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            l10n.colisClaimsSupport,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ),
        Text(
          kColisSupportPhone,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 12,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}
