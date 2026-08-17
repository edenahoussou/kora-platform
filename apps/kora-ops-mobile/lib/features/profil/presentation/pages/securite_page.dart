import 'package:flutter/material.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../injection/injection.dart';
import '../../../auth/domain/usecases/get_saved_email.dart';

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
                    child: const Icon(Icons.pin_outlined,
                        size: 18, color: AppColors.kora),
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
              onPressed: () => KoraDialogs.info(
                context,
                title: 'Bientôt disponible',
                description:
                    'La modification du PIN depuis l\'app sera disponible avec les API '
                    'réelles (Sprint 5). En attendant, contactez votre superviseur d\'agence.',
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            FutureBuilder<String?>(
              future: getIt<GetSavedEmail>()().then(
                (result) => result.when(
                    onSuccess: (email) => email, onFailure: (_) => null),
              ),
              builder: (BuildContext context, AsyncSnapshot<String?> snapshot) {
                final String? email = snapshot.data;
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
                        child: const Icon(Icons.phone_iphone,
                            size: 18, color: AppColors.kora),
                      ),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text('Appareil activé',
                                style: Theme.of(context).textTheme.titleMedium),
                            Text(
                              email ?? 'Aucun appareil activé',
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
