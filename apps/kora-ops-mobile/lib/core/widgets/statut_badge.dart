import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../features/ops/domain/entities/passenger.dart';
import 'kora_tag.dart';

/// Badge de statut d'embarquement — construit sur [KoraTag], avec la
/// couleur sémantique adaptée à chaque [BoardingStatus].
class StatutBadge extends StatelessWidget {
  const StatutBadge({required this.statut, super.key});

  final BoardingStatus statut;

  @override
  Widget build(BuildContext context) {
    final (Color color, IconData icon) = switch (statut) {
      BoardingStatus.embarque => (AppColors.success, Icons.check_circle),
      BoardingStatus.refuse => (AppColors.danger, Icons.cancel),
      BoardingStatus.descenduAnticipe => (AppColors.digital, Icons.move_down),
      BoardingStatus.enAttente => (AppColors.warning, Icons.schedule),
    };

    return KoraTag(label: statut.label, color: color, icon: icon);
  }
}
