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
  late final TextEditingController _sender;
  late final TextEditingController _senderPhone;
  late final TextEditingController _name;
  late final TextEditingController _phone;

  @override
  void initState() {
    super.initState();
    // Les contrôleurs naissent ici, et non dans `build` : en reconstruire
    // un à chaque frappe replacerait le curseur au début du champ.
    final draft = context.read<ColisDraftCubit>().state;
    _sender = TextEditingController(text: draft.senderName);
    _senderPhone = TextEditingController(text: draft.senderPhone);
    _name = TextEditingController(text: draft.recipientName);
    _phone = TextEditingController(text: draft.recipientPhone);
  }

  @override
  void dispose() {
    _sender.dispose();
    _senderPhone.dispose();
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
            hint: l10n.colisContactsHint,
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
              tag: KoraTag(
                label: l10n.colisSenderTag,
                variant: KoraTagVariant.success,
              ),
              children: [
                KoraInput(
                  label: l10n.colisSenderName,
                  controller: _sender,
                  onChanged: cubit.setSenderName,
                ),
                const SizedBox(height: AppSpacing.sm),
                // Verrouillé : le numéro est celui du compte. Le laisser
                // modifier ici enverrait le suivi à quelqu'un d'autre et
                // ferait diverger le reçu du dossier.
                KoraInput(
                  label: l10n.colisSenderPhone,
                  controller: _senderPhone,
                  prefixIcon: Icons.lock_outline_rounded,
                  enabled: false,
                ),
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
              tag: KoraTag(
                label: l10n.colisRecipientTag,
                variant: KoraTagVariant.danger,
              ),
              children: [
                KoraAlert(
                  icon: Icons.badge_outlined,
                  title: l10n.colisIdentityTitle,
                  message: l10n.colisIdentityText,
                ),
                const SizedBox(height: AppSpacing.sm),
                KoraInput(
                  label: l10n.colisRecipientName,
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
                Text(
                  l10n.colisRecipientPhone,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
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
            KoraAlert(
              icon: Icons.sms_outlined,
              title: l10n.colisPickupCodeTitle,
              message: l10n.colisPickupCodeText,
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
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: koraColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Icon(icon, size: 18, color: koraColors.textMuted),
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
