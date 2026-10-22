import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/support_chat_configuration.dart';
import '../../domain/repositories/support_chat_repository.dart';
import '../bloc/support_chat_cubit.dart';

/// Contacter KORA — écran `contact` (PROFILE-06).
///
/// La maquette simule les trois actions par un toast ; ici elles ouvrent
/// vraiment WhatsApp, le composeur et l'application e-mail. Un bouton
/// « Démarrer la conversation » qui ne démarre rien n'aiderait personne.
///
/// Le chat avec un agent s'ajoute à ces canaux **quand le serveur le dit**
/// : il se coupe depuis OPS, et l'entrée disparaît alors d'elle-même.
class ContactPage extends StatelessWidget {
  const ContactPage({super.key, this.launcher, this.onOpenChat, this.cubit});

  /// Injectable pour les tests, qui n'ont pas d'application à ouvrir.
  final Future<bool> Function(Uri)? launcher;

  /// Ouvre la conversation. Reçoit la configuration rendue par le
  /// serveur, que l'écran de chat consomme telle quelle.
  final void Function(SupportChatConfiguration configuration)? onOpenChat;

  /// Cubit déjà construit, pour les tests.
  final SupportChatCubit? cubit;

  /// Numéro de l'assistance, au format international sans séparateur
  /// pour les liens `tel:` et `wa.me`.
  static const String supportPhone = '+22990109648';
  static const String supportEmail = 'contact@kora.express';

  /// Affiché tel quel dans la liste, groupé comme au Bénin.
  static const String supportPhoneLabel = '+229 90 10 96 48';

  /// `wa.me` ouvre l'application quand elle est installée, et le web
  /// sinon.
  Future<void> _openWhatsapp(BuildContext context) => _open(
    context,
    Uri.parse('https://wa.me/${supportPhone.replaceAll('+', '')}'),
    'WhatsApp',
  );

  Future<void> _open(BuildContext context, Uri uri, String target) async {
    final l10n = context.l10n;
    final launch =
        launcher ?? (u) => launchUrl(u, mode: LaunchMode.externalApplication);

    final opened = await launch(uri);
    if (opened || !context.mounted) return;

    // Aucune app ne répond à ce schéma — WhatsApp non installé, tablette
    // sans ligne : on le dit plutôt que de laisser un appui sans effet.
    KoraToaster.show(context, l10n.contactUnavailable(target));
  }

  @override
  Widget build(BuildContext context) {
    final existing = cubit;

    return BlocProvider<SupportChatCubit>(
      create: (_) =>
          (existing ?? SupportChatCubit(getIt<SupportChatRepository>()))
            ..load(),
      child: Builder(builder: _build),
    );
  }

  Widget _build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    // Le chat en direct l'emporte sur WhatsApp quand le serveur le donne
    // : c'est le canal que KORA maîtrise, et il garde le passager dans
    // l'app. WhatsApp reprend la carte quand l'intégration est éteinte,
    // plutôt que de laisser un écran sans canal principal.
    final chat = context.watch<SupportChatCubit>().state;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.contactEyebrow,
                title: l10n.contactTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    KoraCard(
                      variant: KoraCardVariant.navy,
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        children: [
                          // Le carré doré de 67px, `border-radius: 21px`.
                          Container(
                            width: 67,
                            height: 67,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: AppColors.gold,
                              borderRadius: BorderRadius.circular(21),
                            ),
                            child: const Icon(
                              Icons.chat_bubble_rounded,
                              size: 30,
                              color: AppColors.kora,
                            ),
                          ),
                          const SizedBox(height: 16),
                          Text(
                            chat.isAvailable
                                ? l10n.contactLiveTitle
                                : l10n.contactWhatsappTitle,
                            textAlign: TextAlign.center,
                            style: AppTextStyles.heading2(AppColors.white),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            chat.isAvailable
                                ? l10n.contactLiveText
                                : l10n.contactWhatsappText,
                            textAlign: TextAlign.center,
                            style: AppTextStyles.body(
                              AppColors.white.withValues(alpha: 0.72),
                            ),
                          ),
                          const SizedBox(height: 18),
                          KoraButton(
                            label: l10n.contactWhatsappStart,
                            onPressed: () => chat.isAvailable
                                ? onOpenChat?.call(chat.configuration)
                                : _openWhatsapp(context),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Text(
                      l10n.contactOthers,
                      style: AppTextStyles.titleMedium(context.koraTitleColor),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraMenuList(
                      items: [
                        // Le chat ne se répète pas ici : la carte du
                        // haut est devenue son bouton. WhatsApp y prend
                        // sa place, pour que le canal ne disparaisse pas
                        // de l'écran.
                        if (chat.isAvailable)
                          KoraMenuItem(
                            icon: Icons.chat_rounded,
                            title: l10n.contactWhatsappItem,
                            subtitle: supportPhoneLabel,
                            onTap: () => _openWhatsapp(context),
                          ),
                        KoraMenuItem(
                          icon: Icons.call_rounded,
                          title: l10n.contactPhone,
                          subtitle: supportPhoneLabel,
                          onTap: () => _open(
                            context,
                            Uri.parse('tel:$supportPhone'),
                            supportPhoneLabel,
                          ),
                        ),
                        KoraMenuItem(
                          icon: Icons.mail_outline_rounded,
                          title: l10n.contactEmail,
                          subtitle: supportEmail,
                          onTap: () => _open(
                            context,
                            Uri.parse('mailto:$supportEmail'),
                            supportEmail,
                          ),
                        ),
                      ],
                    ),
                    // `style="margin-top:13px"` dans la maquette.
                    const SizedBox(height: 13),
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Text(
                        l10n.contactNote,
                        style: AppTextStyles.body(koraColors.textMuted),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
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
