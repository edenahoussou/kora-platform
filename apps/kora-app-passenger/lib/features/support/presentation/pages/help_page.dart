import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';

/// Une question fréquente et sa réponse.
typedef _Faq = ({String question, String answer});

/// Centre d'aide — écran `help` (PROFILE-05).
///
/// Les réponses sont dans les fichiers de langue plutôt que dans un jeu
/// de données : ce sont des textes, pas des enregistrements, et elles
/// doivent exister dans les deux langues comme le reste de l'app.
class HelpPage extends StatefulWidget {
  const HelpPage({super.key, this.onContact});

  final VoidCallback? onContact;

  @override
  State<HelpPage> createState() => _HelpPageState();
}

class _HelpPageState extends State<HelpPage> {
  final _search = TextEditingController();

  /// Question dépliée. Une seule à la fois : la maquette ouvre la
  /// réponse sous la question, pas un mur de texte.
  String? _expanded;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  List<_Faq> _entries(AppLocalization l10n) => [
    (question: l10n.helpQ1, answer: l10n.helpA1),
    (question: l10n.helpQ2, answer: l10n.helpA2),
    (question: l10n.helpQ3, answer: l10n.helpA3),
    (question: l10n.helpQ4, answer: l10n.helpA4),
    (question: l10n.helpQ5, answer: l10n.helpA5),
  ];

  /// La recherche porte aussi sur les réponses : on cherche « hors
  /// ligne » sans savoir dans quelle question ça se trouve.
  List<_Faq> _matching(AppLocalization l10n) {
    final needle = _search.text.trim().toLowerCase();
    if (needle.isEmpty) return _entries(l10n);

    return _entries(l10n)
        .where(
          (faq) =>
              faq.question.toLowerCase().contains(needle) ||
              faq.answer.toLowerCase().contains(needle),
        )
        .toList();
  }

  /// `faq()` de la maquette : la réponse se déplie sous la question, et
  /// le chevron pivote. Une fabrique et non un widget : `KoraMenuList`
  /// n'accepte que des [KoraMenuItem], pour dessiner ses séparateurs.
  KoraMenuItem _faqItem(BuildContext context, _Faq faq) {
    final isExpanded = _expanded == faq.question;

    return KoraMenuItem(
      icon: Icons.help_outline_rounded,
      title: faq.question,
      subtitle: isExpanded ? faq.answer : null,
      onTap: () => setState(() => _expanded = isExpanded ? null : faq.question),
      trailing: AnimatedRotation(
        duration: const Duration(milliseconds: 150),
        turns: isExpanded ? 0.5 : 0,
        child: Icon(
          Icons.keyboard_arrow_down_rounded,
          size: 20,
          color: context.koraColors.textMuted,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final matches = _matching(l10n);

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(eyebrow: l10n.helpEyebrow, title: l10n.helpTitle),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    KoraInput(
                      hint: l10n.helpSearch,
                      controller: _search,
                      prefixIcon: Icons.search_rounded,
                      onChanged: (_) => setState(() {}),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Text(
                      l10n.helpFaq,
                      style: AppTextStyles.titleMedium(context.koraTitleColor),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    if (matches.isEmpty)
                      KoraCard(
                        variant: KoraCardVariant.mist,
                        child: Text(
                          l10n.helpNoResult,
                          style: AppTextStyles.body(koraColors.textMuted),
                        ),
                      )
                    else
                      KoraMenuList(
                        items: [
                          for (final faq in matches) _faqItem(context, faq),
                        ],
                      ),
                    // `style="margin-top:15px"` dans la maquette.
                    const SizedBox(height: 15),
                    KoraButton(
                      label: l10n.helpContact,
                      onPressed: widget.onContact,
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
