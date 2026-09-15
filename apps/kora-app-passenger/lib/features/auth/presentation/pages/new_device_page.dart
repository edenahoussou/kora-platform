import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/repositories/auth_repository.dart';
import '../bloc/auth_flow_bloc.dart';

/// Vérification d'un appareil inconnu — écran `new-device` (AUTH-10).
///
/// Le serveur refuse d'ouvrir une session sur un appareil qu'il ne
/// connaît pas, **même avec le bon PIN** : il répond 202 et envoie un
/// code. Cet écran explique ce refus avant de demander les six chiffres,
/// pour qu'une saisie de code ne surgisse pas sans raison — et pour que
/// quelqu'un qui ne reconnaît pas la tentative puisse s'arrêter là.
///
/// Il s'ouvre aussi depuis le lien de l'écran de connexion, avant toute
/// tentative : c'est alors son bouton qui demande le code.
class NewDevicePage extends StatefulWidget {
  const NewDevicePage({super.key});

  @override
  State<NewDevicePage> createState() => _NewDevicePageState();
}

class _NewDevicePageState extends State<NewDevicePage> {
  /// Nom lu directement, quand on arrive par l'écran Sécurité : le
  /// parcours d'authentification ne l'a alors pas chargé, n'ayant pas
  /// été traversé.
  String _deviceName = '';

  @override
  void initState() {
    super.initState();
    _loadDeviceName();
  }

  Future<void> _loadDeviceName() async {
    try {
      final device = await getIt<AuthRepository>().currentDevice();
      if (!mounted) return;

      setState(() => _deviceName = device.name);
    } on Object {
      // Un nom illisible ne retient pas l'écran : il affiche un tiret.
    }
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

    // Quitter cet écran doit **remettre le parcours à zéro**, par la
    // flèche comme par le geste du système.
    //
    // Un simple `pop` laissait le Bloc à l'étape `newDevice` : l'écran de
    // connexion revenait, mais toute action qui retombait sur cette même
    // étape — redemander une vérification d'appareil, réinitialiser un PIN
    // sur un appareil inconnu — n'était plus suivie d'aucune navigation,
    // le routeur n'écoutant que les **changements** d'étape. Deux boutons
    // semblaient alors morts.
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _leave(context);
      },
      child: Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                ScreenHeader(
                  eyebrow: l10n.newDeviceEyebrow,
                  title: l10n.newDeviceTitle,
                ),
                Expanded(
                  child: BlocBuilder<AuthFlowBloc, AuthFlowState>(
                    builder: (context, flow) => ListView(
                      padding: EdgeInsets.zero,
                      children: [
                        const _DeviceVisual(),
                        // `.form-title { margin: 18px 0 5px }`
                        const SizedBox(height: 18),
                        Text(
                          l10n.newDeviceQuestion,
                          style: AppTextStyles.heading2(context.koraTitleColor),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          l10n.newDeviceLead,
                          style: AppTextStyles.lead(koraColors.textMuted),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        KoraCard(
                          variant: KoraCardVariant.mist,
                          child: Column(
                            children: [
                              _Row(
                                label: l10n.newDeviceDevice,
                                // Vide tant que le système n'a pas répondu,
                                // ou s'il a échoué : mieux vaut un tiret
                                // qu'un nom inventé sur un écran qui sert
                                // justement à reconnaître son appareil.
                                value: switch ((flow.deviceName, _deviceName)) {
                                  (final fromFlow, _)
                                      when fromFlow.isNotEmpty =>
                                    fromFlow,
                                  (_, final local) when local.isNotEmpty =>
                                    local,
                                  _ => '—',
                                },
                              ),
                              const _Divider(),
                              _Row(
                                label: l10n.newDeviceAttempt,
                                value: l10n.newDeviceAttemptNow,
                              ),
                            ],
                          ),
                        ),
                        if (flow.hasError) ...[
                          const SizedBox(height: 12),
                          Text(
                            flow.errorMessage,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: AppColors.danger,
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                        // `style="margin-top:16px"` dans la maquette.
                        const SizedBox(height: 16),
                        KoraButton(
                          label: l10n.newDeviceReceiveCode,
                          isLoading: flow.isSubmitting,
                          onPressed: flow.isSubmitting
                              ? null
                              : () => context.read<AuthFlowBloc>().add(
                                  const AuthFlowNewDeviceConfirmed(),
                                ),
                        ),
                        const SizedBox(height: 9),
                        KoraButton(
                          label: l10n.newDeviceNotMe,
                          variant: KoraButtonVariant.ghost,
                          // Aucun endpoint ne recueille ce signalement : on
                          // ne prétend pas l'avoir transmis, on arrête le
                          // parcours, ce qui est le geste utile.
                          onPressed: () {
                            KoraToaster.show(context, l10n.newDeviceReported);
                            _leave(context);
                          },
                        ),
                        const SizedBox(height: AppSpacing.lg),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  /// Quitte l'écran en rendant le parcours à son point de départ.
  ///
  /// C'est [AuthFlowReset] qui ramène à la connexion — le routeur suit
  /// l'étape — et non un `pop` : lui seul efface aussi l'adresse retenue,
  /// et rend à l'écran de connexion un formulaire vierge.
  void _leave(BuildContext context) =>
      context.read<AuthFlowBloc>().add(const AuthFlowReset());
}

/// `.security-device-visual` : le bandeau navy au bouclier doré.
class _DeviceVisual extends StatelessWidget {
  const _DeviceVisual();

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      constraints: const BoxConstraints(minHeight: 125),
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
      decoration: BoxDecoration(
        // `background: var(--kora)` est prévu pour une page claire. En
        // thème sombre, c'est la couleur du fond : le bandeau
        // disparaîtrait et il ne resterait que le bouclier flottant. La
        // surface s'en détache à peine, d'où le filet qui en dessine les
        // bords — les cards de l'app en ont un pour la même raison.
        color: isDark ? context.koraColors.surface : AppColors.kora,
        border: isDark ? Border.all(color: context.koraColors.line) : null,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // `.security-device-visual i { width:48px; height:48px }`, avec
          // le fond doré de `.line-shield`.
          Container(
            width: 48,
            height: 48,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.gold,
              borderRadius: BorderRadius.circular(15),
            ),
            child: const Icon(
              Icons.verified_user_outlined,
              size: 26,
              color: AppColors.kora,
            ),
          ),
          const SizedBox(height: 9),
          Text(
            context.l10n.newDeviceVisualCaption,
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

/// `.row` : libellé discret à gauche, valeur en gras à droite.
class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: AppTextStyles.body(koraColors.textMuted)),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: AppTextStyles.titleMedium(context.koraTitleColor),
          ),
        ),
      ],
    );
  }
}

/// `.divider` : le filet entre deux lignes d'une card.
class _Divider extends StatelessWidget {
  const _Divider();

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
    child: Divider(height: 1, thickness: 1, color: context.koraColors.line),
  );
}
