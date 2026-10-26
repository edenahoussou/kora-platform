import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../domain/entities/support_chat_configuration.dart';
import '../../domain/repositories/support_chat_repository.dart';
import '../bloc/support_chat_cubit.dart';

/// Le bouton d'assistance, posé sur les écrans principaux.
///
/// Jusqu'ici, joindre un agent demandait trois niveaux — Profil, Centre
/// d'aide, Contacter KORA. Quelqu'un dont le bus a du retard ne cherche
/// pas dans un menu : il veut un bouton. Celui-ci reste à portée partout
/// où l'on navigue.
///
/// **Il n'apparaît que si le serveur donne un chat.** Même règle que
/// l'entrée de l'écran de contact : intégration éteinte, adresse
/// manquante ou serveur injoignable, et il n'y a rien à montrer. Un
/// bouton flottant qui ne mène nulle part serait pire que pas de bouton
/// du tout, parce qu'il occupe le coin de l'écran en permanence.
class SupportChatFab extends StatelessWidget {
  const SupportChatFab({required this.onOpen, super.key, this.cubit});

  /// Ouvre la conversation avec la configuration rendue par le serveur.
  final void Function(SupportChatConfiguration configuration) onOpen;

  /// Cubit déjà chargé, fourni par l'appelant.
  ///
  /// L'habillage d'application le passe pour que la configuration soit
  /// demandée **une seule fois** : le bouton suit l'utilisateur d'une
  /// page à l'autre, et en reconstruire le cubit à chaque navigation
  /// rappellerait le serveur à chaque écran ouvert.
  final SupportChatCubit? cubit;

  @override
  Widget build(BuildContext context) {
    final existing = cubit;

    // Fourni : il ne nous appartient pas, on ne le ferme pas non plus.
    if (existing != null) {
      return BlocProvider<SupportChatCubit>.value(
        value: existing,
        child: _Fab(onOpen: onOpen),
      );
    }

    return BlocProvider<SupportChatCubit>(
      create: (_) => SupportChatCubit(getIt<SupportChatRepository>())..load(),
      child: _Fab(onOpen: onOpen),
    );
  }
}

class _Fab extends StatelessWidget {
  const _Fab({required this.onOpen});

  final void Function(SupportChatConfiguration configuration) onOpen;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return BlocBuilder<SupportChatCubit, SupportChatState>(
      builder: (context, state) {
        if (!state.isAvailable) return const SizedBox.shrink();

        // `SupportChatOverlay` pose ce bouton au-dessus du `Navigator`, où
        // il n'y a pas d'`Overlay` : une infobulle y lève « No Overlay
        // widget found » — écran rouge en debug, zone grise en release.
        // Là, le libellé passe par l'icône, que les lecteurs d'écran
        // annoncent de la même façon.
        final hasOverlay = Overlay.maybeOf(context) != null;

        return FloatingActionButton(
          onPressed: () => onOpen(state.configuration),
          backgroundColor: AppColors.gold,
          foregroundColor: AppColors.kora,
          // La bulle de l'assistance, pas l'icône générique du casque :
          // c'est une conversation qui s'ouvre, pas un appel.
          tooltip: hasOverlay ? l10n.contactChat : null,
          child: Icon(
            Icons.support_agent_rounded,
            semanticLabel: hasOverlay ? null : l10n.contactChat,
          ),
        );
      },
    );
  }
}
