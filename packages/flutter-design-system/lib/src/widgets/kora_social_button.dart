import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';

/// Les fournisseurs d'identité proposés à l'inscription.
enum KoraSocialProvider { google, facebook, tiktok }

/// Bouton « continuer avec … » : la marque du fournisseur à gauche, le
/// libellé centré, dans le style KORA.
///
/// Le bouton lui-même suit le design system — surface du thème, filet
/// `line`, rayon et hauteur d'un [KoraButton] — parce que c'est un bouton
/// KORA avant d'être un bouton Google : seule la pastille de marque
/// appartient au fournisseur.
///
/// **Les pastilles sont des approximations**, dessinées ici faute des
/// fichiers officiels : chaque fournisseur impose son propre logo et ses
/// règles d'usage (Google Identity, Meta, TikTok for Developers). À
/// remplacer par les assets officiels avant toute mise en production —
/// c'est une exigence de leurs conditions, pas un détail esthétique.
///
/// Exemple d'utilisation :
/// ```dart
/// KoraSocialButton(
///   provider: KoraSocialProvider.google,
///   label: 'Continuer avec Google',
///   onPressed: () {},
/// )
/// ```
class KoraSocialButton extends StatelessWidget {
  const KoraSocialButton({
    super.key,
    required this.provider,
    required this.label,
    required this.onPressed,
    this.isLoading = false,
  });

  final KoraSocialProvider provider;
  final String label;
  final VoidCallback? onPressed;

  /// La feuille du fournisseur est ouverte : le bouton attend sa réponse
  /// et ne réagit plus.
  final bool isLoading;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return SizedBox(
      height: 50,
      child: OutlinedButton(
        onPressed: isLoading ? null : onPressed,
        style: OutlinedButton.styleFrom(
          backgroundColor: koraColors.surface,
          side: BorderSide(color: koraColors.line),
          padding: const EdgeInsets.symmetric(horizontal: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
        ),
        child: Row(
          children: [
            _ProviderMark(provider: provider),
            // Le libellé reste centré sur la largeur du bouton, comme dans
            // la maquette de référence : la pastille ne le décale pas.
            Expanded(
              child: isLoading
                  ? Center(
                      child: SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: isDark
                              ? koraColors.textPrimary
                              : AppColors.kora,
                        ),
                      ),
                    )
                  : Text(
                      label,
                      textAlign: TextAlign.center,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: isDark ? koraColors.textPrimary : AppColors.kora,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
            ),
            // Réserve la largeur de la pastille à droite pour que le
            // centrage porte sur le bouton entier.
            const SizedBox(width: 22),
          ],
        ),
      ),
    );
  }
}

/// La pastille de marque, à gauche du libellé.
class _ProviderMark extends StatelessWidget {
  const _ProviderMark({required this.provider});

  final KoraSocialProvider provider;

  static const double _size = 22;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: _size,
      height: _size,
      child: switch (provider) {
        KoraSocialProvider.google => const _GoogleMark(),
        KoraSocialProvider.facebook => const _FacebookMark(),
        KoraSocialProvider.tiktok => const _TikTokMark(),
      },
    );
  }
}

/// Le « G » de Google. Le logo officiel est quadrichrome ; celui-ci n'en
/// reprend que le bleu (voir la note de [KoraSocialButton]).
class _GoogleMark extends StatelessWidget {
  const _GoogleMark();

  static const _blue = Color(0xFF4285F4);

  @override
  Widget build(BuildContext context) {
    return Container(
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.white,
        shape: BoxShape.circle,
        border: Border.all(color: _blue.withValues(alpha: 0.35)),
      ),
      child: const Text(
        'G',
        style: TextStyle(
          color: _blue,
          fontSize: 14,
          fontWeight: FontWeight.w900,
          height: 1,
        ),
      ),
    );
  }
}

/// Le « f » de Facebook : rond bleu, lettre blanche.
class _FacebookMark extends StatelessWidget {
  const _FacebookMark();

  static const _blue = Color(0xFF1877F2);

  @override
  Widget build(BuildContext context) {
    return Container(
      alignment: Alignment.center,
      decoration: const BoxDecoration(color: _blue, shape: BoxShape.circle),
      child: const Text(
        'f',
        style: TextStyle(
          color: AppColors.white,
          fontSize: 15,
          fontWeight: FontWeight.w900,
          height: 1.1,
        ),
      ),
    );
  }
}

/// La note de TikTok, avec le décalage cyan / rouge qui fait sa marque.
class _TikTokMark extends StatelessWidget {
  const _TikTokMark();

  static const _cyan = Color(0xFF25F4EE);
  static const _red = Color(0xFFFE2C55);

  @override
  Widget build(BuildContext context) {
    return Stack(
      alignment: Alignment.center,
      children: [
        const Positioned(
          left: 1,
          top: 1,
          child: Icon(Icons.music_note_rounded, size: 18, color: _cyan),
        ),
        const Positioned(
          right: 1,
          bottom: 1,
          child: Icon(Icons.music_note_rounded, size: 18, color: _red),
        ),
        Icon(
          Icons.music_note_rounded,
          size: 18,
          color: Theme.of(context).brightness == Brightness.dark
              ? AppColors.white
              : Colors.black,
        ),
      ],
    );
  }
}

/// « ─── ou ─── » : le séparateur entre l'inscription par e-mail et les
/// fournisseurs d'identité.
class KoraOrDivider extends StatelessWidget {
  const KoraOrDivider({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      children: [
        Expanded(child: Divider(color: koraColors.line)),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: Text(
            label,
            style: TextStyle(color: koraColors.textMuted, fontSize: 12),
          ),
        ),
        Expanded(child: Divider(color: koraColors.line)),
      ],
    );
  }
}
