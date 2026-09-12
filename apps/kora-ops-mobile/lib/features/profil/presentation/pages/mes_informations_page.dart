import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';

/// "Mes informations" — identité et coordonnées de l'agent connecté
/// (lecture seule, données mockées à ce stade). Route :
/// `/profil/mes-informations`.
class MesInformationsPage extends StatelessWidget {
  const MesInformationsPage({super.key});

  @override
  Widget build(BuildContext context) {
    final AuthState authState = context.watch<AuthCubit>().state;
    final String fullName =
        authState is AuthAuthenticated ? authState.user.name : '—';
    final String email =
        authState is AuthAuthenticated ? authState.user.email : '—';
    // Le backend ne renvoie plus de libellé de rôle, seulement des
    // permissions — voir AuthUser et le commentaire équivalent dans
    // profil_page.dart.
    const String role = '—';
    final String matricule = authState is AuthAuthenticated
        ? authState.user.uuid.toUpperCase()
        : '—';

    return Scaffold(
      appBar:
          const ScreenHeader(eyebrow: 'Mon compte', title: 'Mes informations'),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: <Widget>[
            KoraCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  _InfoRow(
                      icon: Icons.badge_outlined,
                      label: 'Nom complet',
                      value: fullName),
                  const Divider(height: AppSpacing.lg),
                  _InfoRow(
                      icon: Icons.email_outlined,
                      label: 'Adresse email',
                      value: email),
                  const Divider(height: AppSpacing.lg),
                  const _InfoRow(
                      icon: Icons.work_outline, label: 'Rôle', value: role),
                  const Divider(height: AppSpacing.lg),
                  _InfoRow(
                      icon: Icons.tag, label: 'Matricule', value: matricule),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              'Ces informations sont provisionnées par votre administrateur d\'agence. '
              'Contactez-le pour toute correction.',
              style:
                  TextStyle(color: context.koraColors.textMuted, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow(
      {required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Container(
          width: 36,
          height: 36,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: context.koraColors.background,
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          child: Icon(icon, size: 18, color: AppColors.kora),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(label,
                  style: TextStyle(
                      color: context.koraColors.textMuted, fontSize: 11)),
              Text(value, style: Theme.of(context).textTheme.titleMedium),
            ],
          ),
        ),
      ],
    );
  }
}
