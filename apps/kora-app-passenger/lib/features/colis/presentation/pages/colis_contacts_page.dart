import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../auth/presentation/widgets/phone_country_picker.dart';
import '../bloc/colis_draft_cubit.dart';
import '../widgets/colis_step_scaffold.dart';
import '../widgets/colis_surface.dart';

/// Écran 04 — « Qui envoie et qui reçoit ? ».
///
/// L'expéditeur vient du compte et ne se retouche pas : c'est son
/// téléphone qui recevra le suivi et son nom qui figurera sur le reçu.
/// Le destinataire, lui, est à saisir — et son nom doit correspondre à
/// une pièce d'identité, puisque c'est elle qu'on présentera au retrait.
class ColisContactsPage extends StatefulWidget {
  const ColisContactsPage({super.key});

  @override
  State<ColisContactsPage> createState() => _ColisContactsPageState();
}

class _ColisContactsPageState extends State<ColisContactsPage> {
  late final TextEditingController _name;
  late final TextEditingController _phone;

  @override
  void initState() {
    super.initState();
    // Les contrôleurs naissent ici, et non dans `build` : en reconstruire
    // un à chaque frappe replacerait le curseur au début du champ.
    final draft = context.read<ColisDraftCubit>().state;
    _name = TextEditingController(text: draft.recipientName);
    _phone = TextEditingController(text: draft.recipientPhone);
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) {
        final cubit = context.read<ColisDraftCubit>();

        return ColisStepScaffold(
          step: 3,
          stepLabel: l10n.colisStepContacts,
          title: l10n.colisContactsTitle,
          subtitle: l10n.colisContactsSubtitle,
          footer: ColisStepFooter(
            label: l10n.colisContactsCta,
            onContinue: draft.areContactsComplete
                ? () => colisAdvance(context, AppRouter.colisSummaryPath)
                : null,
            onCancel: () => context.go('/colis'),
          ),
          children: [
            _ContactCard(
              icon: Icons.person_outline_rounded,
              title: l10n.colisSenderTitle,
              subtitle: l10n.colisSenderSubtitle,
              tag: _StatePill(
                label: l10n.colisSenderTag,
                color: AppColors.success,
                icon: Icons.check_circle_rounded,
              ),
              children: [
                // Rien ne se modifie ici : le nom et le numéro viennent
                // du compte, et c'est eux qui porteront le suivi et le
                // reçu. Deux champs de saisie promettaient le
                // contraire — on reprend le bloc du récapitulatif, qui
                // se lit et ne s'ouvre pas.
                _SenderBlock(name: draft.senderName, phone: draft.senderPhone),
                const SizedBox(height: 6),
                Text(
                  l10n.colisSenderPhoneHelp,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            _ContactCard(
              icon: Icons.storefront_outlined,
              title: l10n.colisRecipientTitle,
              subtitle: l10n.colisRecipientSubtitle,
              tag: _StatePill(
                label: l10n.colisRecipientTag,
                color: AppColors.danger,
                icon: Icons.priority_high_rounded,
              ),
              children: [
                ColisNote(
                  icon: Icons.badge_outlined,
                  title: l10n.colisIdentityTitle,
                  message: l10n.colisIdentityText,
                ),
                const SizedBox(height: AppSpacing.sm),
                // Le champ KORA, comme partout ailleurs dans l'app :
                // même intitulé, même contour, même état de faute.
                KoraInput(
                  label: l10n.colisRecipientName,
                  required: true,
                  hint: l10n.colisRecipientNameHint,
                  controller: _name,
                  onChanged: (value) => cubit.setRecipient(name: value),
                ),
                const SizedBox(height: 6),
                Text(
                  l10n.colisRecipientNameHelp,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
                const SizedBox(height: AppSpacing.sm),
                // L'indicatif se choisit comme à l'inscription : un
                // colis se retire souvent pour un proche joignable hors
                // du Bénin, et un numéro sans son pays ne se compose
                // pas depuis le guichet.
                // L'astérisque rouge, comme sur le champ du nom : sans
                // ce numéro, le destinataire ne reçoit pas son code de
                // retrait, et l'écran ne laisse pas continuer.
                RichText(
                  text: TextSpan(
                    text: l10n.colisRecipientPhone,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                    children: const [
                      TextSpan(
                        text: ' *',
                        style: TextStyle(color: AppColors.danger),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    PhoneCountryButton(
                      country: draft.recipientCountry,
                      onCountry: cubit.setRecipientCountry,
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: KoraInput(
                        hint: draft.recipientCountry.example,
                        controller: _phone,
                        keyboardType: TextInputType.phone,
                        onChanged: (value) => cubit.setRecipient(phone: value),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            // Le code de retrait ne transite jamais par l'app de
            // l'expéditeur : c'est ce qui le rend opposable au guichet.
            ColisNote(
              icon: Icons.sms_outlined,
              title: l10n.colisPickupCodeTitle,
              message: l10n.colisPickupCodeText,
            ),
            const SizedBox(height: AppSpacing.sm),
            // Ce qu'on fait des deux numéros qu'on vient de demander :
            // la question se pose au moment de les saisir, pas après.
            ColisNote(
              icon: Icons.security_outlined,
              title: l10n.colisPrivacyTitle,
              message: l10n.colisPrivacyText,
            ),
          ],
        );
      },
    );
  }
}

/// Une partie du formulaire : son icône, son intitulé et son état.
class _ContactCard extends StatelessWidget {
  const _ContactCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.tag,
    required this.children,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Widget tag;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
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
              // La tuile grise de la maquette : le pictogramme y est
              // posé, pas lâché à côté du titre.
              Container(
                width: 36,
                height: 36,
                alignment: Alignment.center,
                decoration: colisInsetDecoration(context),
                child: Icon(icon, size: 20, color: context.koraTitleColor),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              tag,
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          ...children,
        ],
      ),
    );
  }
}

/// L'expéditeur, tel que le compte le connaît.
///
/// Un seul bloc gris, comme le récapitulatif en affiche un par partie :
/// ni le nom ni le numéro ne se retouchent ici — le premier figurera sur
/// le reçu, le second portera le suivi, et tous deux viennent du compte.
/// Le cadenas le dit sans phrase.
class _SenderBlock extends StatelessWidget {
  const _SenderBlock({required this.name, required this.phone});

  final String name;
  final String phone;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: colisInsetDecoration(context),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.colisSenderName,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
                const SizedBox(height: 2),
                Text(
                  name,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 9),
                Text(
                  l10n.colisSenderPhone,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    // Le bouclier : ce numéro est celui d'un compte
                    // vérifié, pas une ligne saisie à la main.
                    Icon(
                      Icons.verified_user_outlined,
                      size: 16,
                      color: koraColors.textMuted,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        phone,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Icon(
            Icons.lock_outline_rounded,
            size: 18,
            color: koraColors.textMuted,
          ),
        ],
      ),
    );
  }
}

/// La pastille d'état d'une section : une puce, un mot.
///
/// Fond gris et texte coloré, comme la maquette — un pavé plein vert ou
/// rouge pesait plus lourd que le titre qu'il accompagne.
class _StatePill extends StatelessWidget {
  const _StatePill({
    required this.label,
    required this.color,
    required this.icon,
  });

  final String label;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: koraColors.background,
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}
