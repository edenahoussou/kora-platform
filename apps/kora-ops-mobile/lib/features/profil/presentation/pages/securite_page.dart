import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../injection/injection.dart';
import '../../../auth/domain/entities/auth_session.dart';
import '../../../auth/domain/repositories/auth_repository.dart';

/// "Sécurité" — PIN, email et appareils. Route : `/profil/securite`.
class SecuritePage extends StatelessWidget {
  const SecuritePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const ScreenHeader(eyebrow: 'Mon compte', title: 'Sécurité'),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: <Widget>[
            KoraCard(
              child: Row(
                children: <Widget>[
                  Container(
                    width: 36,
                    height: 36,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: context.koraColors.background,
                      borderRadius: BorderRadius.circular(AppRadius.md),
                    ),
                    child: Icon(Icons.pin_outlined,
                        size: 18, color: context.koraColors.textPrimary),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text('PIN KORA',
                            style: Theme.of(context).textTheme.titleMedium),
                        Text(
                          'Défini — utilisé à chaque connexion',
                          style: TextStyle(
                              color: context.koraColors.textMuted,
                              fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            KoraButton(
              label: 'Modifier mon PIN',
              variant: KoraButtonVariant.secondary,
              onPressed: () => context.push(AppRoutes.changePin),
            ),
            const SizedBox(height: AppSpacing.lg),
            FutureBuilder<AuthSession?>(
              future: getIt<AuthRepository>().currentSession().then((result) =>
                  result.when(
                      onSuccess: (session) => session, onFailure: (_) => null)),
              builder:
                  (BuildContext context, AsyncSnapshot<AuthSession?> snapshot) {
                final String? deviceName = snapshot.data?.device?.name;
                return KoraCard(
                  child: Row(
                    children: <Widget>[
                      Container(
                        width: 36,
                        height: 36,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: context.koraColors.background,
                          borderRadius: BorderRadius.circular(AppRadius.md),
                        ),
                        child: Icon(Icons.phone_iphone,
                            size: 18, color: context.koraColors.textPrimary),
                      ),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text('Appareil activé',
                                style: Theme.of(context).textTheme.titleMedium),
                            Text(
                              deviceName ?? 'Aucun appareil activé',
                              style: TextStyle(
                                  color: context.koraColors.textMuted,
                                  fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
